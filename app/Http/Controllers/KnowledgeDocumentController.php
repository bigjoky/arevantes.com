<?php

namespace App\Http\Controllers;

use App\Models\AiFeedback;
use App\Models\KnowledgeDocument;
use App\Services\Ai\KnowledgeIndexer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
                ->withCount('chunks')
                ->latest()
                ->get()
                ->map(fn (KnowledgeDocument $document) => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'original_filename' => $document->original_filename,
                    'source_url' => $document->source_url,
                    'file_size' => $document->file_size,
                    'version' => $document->version,
                    'embedding_model' => $document->embedding_model,
                    'indexed_at' => $document->indexed_at?->toIso8601String(),
                    'chunks_count' => $document->chunks_count,
                    'user_name' => $document->user?->name,
                ]),
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

    public function store(Request $request, KnowledgeIndexer $indexer): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'content' => ['nullable', 'string', 'max:200000'],
            'document' => ['nullable', 'file', 'mimes:txt,md', 'max:10240'],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'],
        ]);
        abort_if(blank($data['content'] ?? null) && blank($data['source_url'] ?? null) && ! $request->hasFile('document'), 422, __('Add text, a public URL, or select a document.'));

        $file = $request->file('document');
        $sourceUrl = filled($data['source_url'] ?? null) ? trim($data['source_url']) : null;
        $content = match (true) {
            (bool) $file => trim($file->get()),
            (bool) $sourceUrl => $this->contentFromUrl($sourceUrl),
            default => trim($data['content']),
        };
        abort_if($content === '', 422, __('The document is empty.'));

        $filename = Str::uuid().'.'.($file?->getClientOriginalExtension() ?: 'md');
        $path = 'knowledge/'.$filename;
        Storage::disk('local')->put($path, $content);
        $document = new KnowledgeDocument([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'source' => Storage::disk('local')->path($path),
            'source_url' => $sourceUrl,
            'original_filename' => $file?->getClientOriginalName(),
            'file_size' => strlen($content),
        ]);

        try {
            $indexer->indexContent($document, $content, $data['title']);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', __('Knowledge indexed successfully.'));
    }

    private function contentFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        abort_unless(is_string($host) && $this->isPublicHost($host), 422, __('Only public web sources can be indexed.'));
        $response = Http::timeout(20)
            ->connectTimeout(5)
            ->withOptions(['allow_redirects' => false])
            ->withUserAgent('Arevantes-Arevia-Knowledge/1.0')
            ->get($url);
        abort_unless($response->successful(), 422, __('The source could not be downloaded. Use its final public URL if it redirects.'));
        abort_if(strlen($response->body()) > 5_000_000, 422, __('The web source is too large.'));
        $contentType = strtolower($response->header('Content-Type'));
        abort_unless(str_contains($contentType, 'text/html') || str_contains($contentType, 'text/plain'), 422, __('The URL must contain an HTML or plain-text page.'));

        $text = str_contains($contentType, 'text/html')
            ? html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $response->body())), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $response->body();

        return trim(preg_replace('/[\t ]+/', ' ', preg_replace('/\R{3,}/', "\n\n", $text)));
    }

    private function isPublicHost(string $host): bool
    {
        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        return $addresses !== [] && collect($addresses)->every(fn (string $address) => filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false);
    }

    public function reindex(KnowledgeDocument $knowledgeDocument, KnowledgeIndexer $indexer): RedirectResponse
    {
        abort_unless(is_file($knowledgeDocument->source), 422, __('The source file is no longer available.'));
        $indexer->index($knowledgeDocument->source, true);

        return back()->with('success', __('Knowledge reindexed successfully.'));
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
