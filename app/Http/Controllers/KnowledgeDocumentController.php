<?php

namespace App\Http\Controllers;

use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\KnowledgeDocument;
use App\Rules\SafeUpload;
use App\Services\Ai\KnowledgeIndexer;
use App\Services\Ai\KnowledgeSourceLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KnowledgeDocumentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('knowledge/index', [
            'documents' => KnowledgeDocument::query()
                ->with('user:id,name')
                ->withCount(['chunks', 'versions'])
                ->latest()
                ->get()
                ->map(fn (KnowledgeDocument $document) => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'area' => $document->area,
                    'allowed_roles' => $document->allowed_roles,
                    'confidentiality' => $document->confidentiality,
                    'status' => $document->stale_at?->isPast() && $document->status === 'indexed' ? 'stale' : $document->status,
                    'failure_reason' => $document->failure_reason,
                    'original_filename' => $document->original_filename,
                    'source_url' => $document->source_url,
                    'file_size' => $document->file_size,
                    'version' => $document->version,
                    'embedding_model' => $document->embedding_model,
                    'indexed_at' => $document->indexed_at?->toIso8601String(),
                    'chunks_count' => $document->chunks_count,
                    'versions_count' => $document->versions_count,
                    'user_name' => $document->user?->name,
                ]),
            'queue' => KnowledgeDocument::query()
                ->selectRaw('status, count(*) total')
                ->groupBy('status')->pluck('total', 'status'),
            'metrics' => [
                'responses' => AiMessage::where('role', 'assistant')->where('status', 'completed')->count(),
                'average_score' => round((float) AiMessage::where('role', 'assistant')->whereNotNull('automated_score')->avg('automated_score'), 1),
                'average_duration_ms' => (int) AiMessage::where('role', 'assistant')->whereNotNull('duration_ms')->avg('duration_ms'),
                'cost_micros' => (int) AiMessage::where('role', 'assistant')->sum('cost_micros'),
                'human_reviews' => AiMessage::where('role', 'assistant')->whereNotNull('human_score')->count(),
            ],
            'evaluationQuestions' => config('arevia.evaluation_questions'),
            'pendingFeedback' => AiFeedback::query()
                ->with(['user:id,name', 'generation.installation:id,project_reference,service_type'])
                ->where('rating', 'needs_correction')
                ->whereNull('approved_at')
                ->latest()
                ->get()
                ->map(fn (AiFeedback $feedback) => [
                    'id' => $feedback->id,
                    'installation_id' => $feedback->generation->installation_id,
                    'installation_reference' => $feedback->generation->installation->project_reference,
                    'service_type' => $feedback->generation->installation->service_type,
                    'reviewer' => $feedback->user->name,
                    'notes' => $feedback->notes,
                    'original_response' => $feedback->original_response,
                    'corrected_response' => $feedback->corrected_response,
                    'created_at' => $feedback->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, KnowledgeIndexer $indexer, KnowledgeSourceLoader $sourceLoader): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'content' => ['nullable', 'string', 'max:200000'],
            'document' => ['nullable', 'file', new SafeUpload(['txt', 'md'], 10240)],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'],
            'area' => ['required', 'in:general,crm,installations,finance,operations'],
            'confidentiality' => ['required', 'in:internal,restricted'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['in:admin,commercial,technician'],
        ]);
        abort_if(blank($data['content'] ?? null) && blank($data['source_url'] ?? null) && ! $request->hasFile('document'), 422, __('Add text, a public URL, or select a document.'));

        $file = $request->file('document');
        $sourceUrl = filled($data['source_url'] ?? null) ? trim($data['source_url']) : null;
        $content = match (true) {
            (bool) $file => trim($file->get()),
            (bool) $sourceUrl => $sourceLoader->fromUrl($sourceUrl),
            default => trim($data['content']),
        };
        abort_if($content === '', 422, __('The document is empty.'));

        $filename = Str::uuid().'.'.($file?->getClientOriginalExtension() ?: 'md');
        $path = 'knowledge/'.$filename;
        Storage::disk('local')->put($path, $content);
        $document = new KnowledgeDocument([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'area' => $data['area'],
            'allowed_roles' => array_values($data['allowed_roles'] ?? []),
            'confidentiality' => $data['confidentiality'],
            'status' => 'pending',
            'source' => Storage::disk('local')->path($path),
            'source_url' => $sourceUrl,
            'original_filename' => $file?->getClientOriginalName(),
            'file_size' => strlen($content),
        ]);

        try {
            $indexer->indexContent($document, $content, $data['title']);
        } catch (Throwable $exception) {
            $document->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 1000)]);

            return back()->with('error', __('The document was queued as failed. Review the error and retry indexing.'));
        }

        return back()->with('success', $document->status === 'quarantined'
            ? __('The document was quarantined because it contains suspicious instructions.')
            : __('Knowledge indexed successfully.'));
    }

    public function reindex(KnowledgeDocument $knowledgeDocument, KnowledgeIndexer $indexer, KnowledgeSourceLoader $sourceLoader): RedirectResponse
    {
        abort_unless(is_file($knowledgeDocument->source), 422, __('The source file is no longer available.'));
        try {
            $knowledgeDocument->update(['status' => 'processing', 'failure_reason' => null]);
            if ($knowledgeDocument->source_url) {
                $content = $sourceLoader->fromUrl($knowledgeDocument->source_url);
                Storage::disk('local')->put('knowledge/'.basename($knowledgeDocument->source), $content);
                $knowledgeDocument->update(['file_size' => strlen($content)]);
                $indexer->indexContent($knowledgeDocument, $content, $knowledgeDocument->title, true, filled($knowledgeDocument->embedding_model));
            } else {
                $indexer->index($knowledgeDocument->source, true, filled($knowledgeDocument->embedding_model));
            }
        } catch (Throwable $exception) {
            $knowledgeDocument->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 1000)]);

            return back()->with('error', __('Reindexing failed. The document remains in the failed queue.'));
        }

        return back()->with('success', __('Knowledge reindexed successfully.'));
    }

    public function update(Request $request, KnowledgeDocument $knowledgeDocument, KnowledgeIndexer $indexer, KnowledgeSourceLoader $sourceLoader): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'area' => ['required', 'in:general,crm,installations,finance,operations'],
            'confidentiality' => ['required', 'in:internal,restricted'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['in:admin,commercial,technician'],
            'content' => ['nullable', 'string', 'max:200000'],
            'document' => ['nullable', 'file', 'mimes:txt,md', 'max:10240'],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'],
        ]);
        $knowledgeDocument->update([
            'title' => $data['title'],
            'area' => $data['area'],
            'allowed_roles' => array_values($data['allowed_roles'] ?? []),
            'confidentiality' => $data['confidentiality'],
        ]);

        $file = $request->file('document');
        $sourceUrl = filled($data['source_url'] ?? null) ? trim($data['source_url']) : null;
        if (! $file && blank($data['content'] ?? null) && ! $sourceUrl) {
            return back()->with('success', __('Knowledge access settings updated.'));
        }

        try {
            $content = match (true) {
                (bool) $file => trim($file->get()),
                (bool) $sourceUrl => $sourceLoader->fromUrl($sourceUrl),
                default => trim($data['content']),
            };
            abort_if($content === '', 422, __('The document is empty.'));
            $path = 'knowledge/'.Str::uuid().'.'.($file?->getClientOriginalExtension() ?: 'md');
            Storage::disk('local')->put($path, $content);
            $knowledgeDocument->update([
                'source' => Storage::disk('local')->path($path),
                'source_url' => $sourceUrl,
                'original_filename' => $file?->getClientOriginalName(),
                'file_size' => strlen($content),
                'status' => 'pending',
            ]);
            $indexer->indexContent($knowledgeDocument, $content, $data['title'], withEmbeddings: filled($knowledgeDocument->embedding_model));
        } catch (Throwable $exception) {
            $knowledgeDocument->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 1000)]);

            return back()->with('error', __('The new version could not be indexed and remains in the failed queue.'));
        }

        return back()->with('success', $knowledgeDocument->status === 'quarantined'
            ? __('The new version was quarantined because it contains suspicious instructions.')
            : __('A new knowledge version was indexed.'));
    }

    public function destroy(KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $storageRoot = realpath(Storage::disk('local')->path('knowledge'));
        $source = realpath($knowledgeDocument->source);
        if ($storageRoot && $source && str_starts_with($source, $storageRoot.DIRECTORY_SEPARATOR)) {
            @unlink($source);
        }
        $knowledgeDocument->delete();

        return back()->with('success', __('Knowledge removed.'));
    }
}
