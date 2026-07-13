<?php

namespace App\Http\Controllers;

use App\Models\AiFeedback;
use App\Models\AiGeneration;
use App\Models\Installation;
use App\Models\KnowledgeDocument;
use App\Services\Ai\KnowledgeIndexer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiFeedbackController extends Controller
{
    public function store(Request $request, Installation $installation, AiGeneration $aiGeneration): JsonResponse
    {
        abort_unless($aiGeneration->installation_id === $installation->id && $aiGeneration->status === 'completed', 404);
        $data = $request->validate([
            'rating' => ['required', 'in:helpful,needs_correction'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'summary' => ['nullable', 'string', 'max:4000'],
            'risks' => ['nullable', 'string', 'max:8000'],
            'next_actions' => ['nullable', 'string', 'max:8000'],
        ]);

        $original = $aiGeneration->structured_response ?? [];
        $corrected = $data['rating'] === 'needs_correction' ? array_replace($original, [
            'summary' => filled($data['summary'] ?? null) ? trim($data['summary']) : ($original['summary'] ?? ''),
            'risks' => $this->lines($data['risks'] ?? null, $original['risks'] ?? []),
            'next_actions' => $this->lines($data['next_actions'] ?? null, $original['next_actions'] ?? []),
        ]) : null;

        $feedback = AiFeedback::updateOrCreate(
            ['ai_generation_id' => $aiGeneration->id, 'user_id' => $request->user()->id],
            ['rating' => $data['rating'], 'notes' => $data['notes'] ?? null, 'original_response' => $original, 'corrected_response' => $corrected,
                'approved_by' => null, 'approved_at' => null, 'knowledge_document_id' => null],
        );

        return response()->json(['feedback' => $this->payload($feedback)], 201);
    }

    public function approve(Request $request, AiFeedback $aiFeedback, KnowledgeIndexer $indexer): RedirectResponse
    {
        abort_if($aiFeedback->rating !== 'needs_correction' || ! $aiFeedback->corrected_response, 422);
        $aiFeedback->load('generation.installation');
        $content = $this->trainingDocument($aiFeedback);
        $filename = Str::uuid().'.md';
        $path = 'knowledge/'.$filename;
        Storage::disk('local')->put($path, $content);
        $document = new KnowledgeDocument([
            'user_id' => $request->user()->id,
            'title' => 'Arevia correction '.$aiFeedback->generation->uuid,
            'source' => Storage::disk('local')->path($path),
            'file_size' => strlen($content),
        ]);
        $indexer->indexContent($document, $content, $document->title);
        $aiFeedback->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'knowledge_document_id' => $document->id,
        ]);

        return back()->with('success', __('Correction approved and added to Arevia knowledge.'));
    }

    private function lines(?string $value, array $fallback): array
    {
        return filled($value)
            ? collect(preg_split('/\R/', $value))->map(fn (string $line) => trim($line))->filter()->values()->all()
            : $fallback;
    }

    private function trainingDocument(AiFeedback $feedback): string
    {
        $result = $feedback->corrected_response;

        return "# Validated Arevia correction\n\nService: {$feedback->generation->installation->service_type}\n".
            "Installation status: {$feedback->generation->installation->status}\n\n".
            "## Validated summary\n{$result['summary']}\n\n".
            "## Validated risks\n".collect($result['risks'] ?? [])->map(fn ($item) => '- '.$item)->implode("\n")."\n\n".
            "## Validated next actions\n".collect($result['next_actions'] ?? [])->map(fn ($item) => '- '.$item)->implode("\n")."\n\n".
            ($feedback->notes ? "## Reviewer notes\n{$feedback->notes}\n" : '');
    }

    private function payload(AiFeedback $feedback): array
    {
        return ['id' => $feedback->id, 'rating' => $feedback->rating, 'notes' => $feedback->notes, 'approved_at' => $feedback->approved_at?->toIso8601String()];
    }
}
