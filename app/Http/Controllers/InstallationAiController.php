<?php

namespace App\Http\Controllers;

use App\Ai\Agents\InstallationAdvisor;
use App\Ai\Agents\InstallationSnapshot;
use App\Jobs\GenerateInstallationBriefing;
use App\Models\AiAuditLog;
use App\Models\AiGeneration;
use App\Models\CrmActivity;
use App\Models\Installation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class InstallationAiController extends Controller
{
    public function briefing(Request $request, Installation $installation): JsonResponse
    {
        $mode = $request->validate(['mode' => ['nullable', 'in:quick,operational']])['mode'] ?? 'operational';
        if (! InstallationAdvisor::isFaked() && ! InstallationSnapshot::isFaked() && ! $this->providerIsConfigured()) {
            return response()->json(['message' => __('The AI provider is not configured.')], 422);
        }

        $generation = $installation->aiGenerations()->create([
            'user_id' => $request->user()->id,
            'status' => 'queued',
            'mode' => $mode,
            'prompt_version' => $mode === 'quick' ? 'installation-snapshot-v1' : 'installation-operational-v1',
        ]);
        GenerateInstallationBriefing::dispatch($generation->id);

        return response()->json(['generation' => $this->payload($generation->refresh())], 202);
    }

    public function status(Installation $installation, AiGeneration $aiGeneration): JsonResponse
    {
        abort_unless($aiGeneration->installation_id === $installation->id, 404);

        return response()->json(['generation' => $this->payload($aiGeneration)]);
    }

    public function createActivities(Request $request, Installation $installation, AiGeneration $aiGeneration): JsonResponse
    {
        abort_unless($aiGeneration->installation_id === $installation->id, 404);
        abort_unless($aiGeneration->status === 'completed', 422);
        if (! $request->boolean('confirmed')) {
            return response()->json(['message' => __('Human confirmation is required before Arevia can modify CRM data.')], 422);
        }

        $indexes = collect($request->validate([
            'indexes' => ['nullable', 'array'],
            'indexes.*' => ['integer', 'min:0'],
        ])['indexes'] ?? []);
        $items = collect($aiGeneration->structured_response['action_items'] ?? [])
            ->when($indexes->isNotEmpty(), fn ($items) => $items->filter(fn ($item, $index) => $indexes->contains($index)))
            ->take(10);

        $created = $items->map(function (array $item) use ($request, $installation, $aiGeneration) {
            $title = trim((string) Arr::get($item, 'title', 'AI suggested action'));
            $notes = trim((string) Arr::get($item, 'reason', 'Suggested by AI briefing.'));
            $type = in_array(Arr::get($item, 'type'), ['task', 'visit', 'reminder'], true) ? Arr::get($item, 'type') : 'task';
            $priority = in_array(Arr::get($item, 'priority'), ['low', 'normal', 'high'], true) ? Arr::get($item, 'priority') : 'normal';
            $dueInDays = min(max((int) Arr::get($item, 'due_in_days', 3), 0), 90);

            return CrmActivity::firstOrCreate(
                [
                    'installation_id' => $installation->id,
                    'title' => $title,
                    'status' => 'pending',
                ],
                [
                    'person_id' => $installation->person_id,
                    'assigned_user_id' => $request->user()->id,
                    'created_by' => $request->user()->id,
                    'type' => $type,
                    'notes' => $notes."\n\nAI briefing: {$aiGeneration->uuid}",
                    'priority' => $priority,
                    'due_at' => now()->addDays($dueInDays),
                ],
            );
        })->values();

        AiAuditLog::create([
            'user_id' => $request->user()->id,
            'event' => 'action_confirmed',
            'context_module' => 'installations',
            'sources' => $aiGeneration->sources,
            'response' => $aiGeneration->raw_response,
            'action' => 'create_crm_activities',
            'confirmed_at' => now(),
            'metadata' => ['generation_uuid' => $aiGeneration->uuid, 'installation_id' => $installation->id, 'created_ids' => $created->pluck('id')->all()],
        ]);

        return response()->json([
            'created' => $created->map(fn (CrmActivity $activity) => [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type,
                'priority' => $activity->priority,
                'due_at' => $activity->due_at?->toIso8601String(),
            ]),
        ], 201);
    }

    public static function payload(?AiGeneration $generation): ?array
    {
        if (! $generation) {
            return null;
        }

        return [
            'uuid' => $generation->uuid,
            'status' => $generation->status,
            'mode' => $generation->mode,
            'result' => $generation->structured_response,
            'sources' => collect($generation->sources ?? [])->map(fn (array $source) => [
                'title' => $source['title'], 'version' => $source['version'], 'score' => $source['score'],
                'excerpt' => $source['excerpt'] ?? mb_substr(trim(preg_replace('/\s+/u', ' ', $source['content'] ?? '')), 0, 360),
                'url' => isset($source['document_id']) ? route('arevia.sources.show', [$source['document_id'], $source['version']]) : null,
            ])->all(),
            'model' => $generation->model,
            'duration_ms' => $generation->duration_ms,
            'message' => $generation->status === 'failed'
                ? (str_contains((string) $generation->error, 'timed out')
                    ? __('Arevia needed more time than expected. Please try the quick summary or run the operational plan again.')
                    : __('The AI briefing could not be generated right now.'))
                : null,
        ];
    }

    private function providerIsConfigured(): bool
    {
        $configuration = config('ai.providers.'.config('ai.default'), []);

        return ($configuration['driver'] ?? null) === 'ollama'
            ? filled($configuration['url'] ?? null)
            : filled($configuration['key'] ?? null);
    }
}
