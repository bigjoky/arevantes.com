<?php

namespace App\Services\Ai;

use App\Models\AiActionProposal;
use App\Models\AiGeneration;
use App\Models\CrmActivity;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiActionProposalService
{
    public function proposeFor(AiGeneration $generation): Collection
    {
        $now = now();
        $rows = collect($generation->structured_response['action_items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->take(10)
            ->values()
            ->map(function (array $item, int $position) use ($generation, $now) {
                $payload = $this->normalize($item);

                return [
                    'uuid' => (string) Str::uuid(),
                    'ai_generation_id' => $generation->id,
                    'installation_id' => $generation->installation_id,
                    'proposed_by' => $generation->user_id,
                    'action' => 'create_crm_activity',
                    'status' => 'proposed',
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'idempotency_key' => hash('sha256', $generation->id.'|'.$position.'|'.json_encode($payload, JSON_THROW_ON_ERROR)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });

        if ($rows->isEmpty()) {
            return collect();
        }

        AiActionProposal::query()->upsert(
            $rows->all(),
            ['idempotency_key'],
            [],
        );

        return AiActionProposal::query()
            ->where('ai_generation_id', $generation->id)
            ->orderBy('id')
            ->get();
    }

    public function execute(AiGeneration $generation, User $user, array $proposalUuids): Collection
    {
        $proposalUuids = collect($proposalUuids)->unique()->values();

        return DB::transaction(function () use ($generation, $user, $proposalUuids) {
            $proposals = AiActionProposal::query()
                ->where('ai_generation_id', $generation->id)
                ->where('installation_id', $generation->installation_id)
                ->whereIn('uuid', $proposalUuids)
                ->lockForUpdate()
                ->get();

            if ($proposals->count() !== $proposalUuids->count()) {
                throw ValidationException::withMessages([
                    'proposal_uuids' => __('One or more action proposals are invalid or no longer available.'),
                ]);
            }

            return $proposalUuids->map(function (string $uuid) use ($proposals, $generation, $user) {
                $proposal = $proposals->firstWhere('uuid', $uuid);

                if ($proposal->status === 'executed' && $proposal->crm_activity_id) {
                    return CrmActivity::findOrFail($proposal->crm_activity_id);
                }

                if ($proposal->status !== 'proposed') {
                    throw ValidationException::withMessages([
                        'proposal_uuids' => __('An action proposal cannot be executed in its current state.'),
                    ]);
                }

                $payload = $proposal->payload;
                $activity = CrmActivity::create([
                    'installation_id' => $generation->installation_id,
                    'person_id' => $generation->installation->person_id,
                    'assigned_user_id' => $user->id,
                    'created_by' => $user->id,
                    'type' => $payload['type'],
                    'title' => $payload['title'],
                    'notes' => $payload['reason']."\n\nAI proposal: {$proposal->uuid}\nAI briefing: {$generation->uuid}",
                    'priority' => $payload['priority'],
                    'status' => 'pending',
                    'due_at' => now()->addDays($payload['due_in_days']),
                ]);

                $proposal->update([
                    'status' => 'executed',
                    'confirmed_by' => $user->id,
                    'confirmed_at' => now(),
                    'executed_at' => now(),
                    'crm_activity_id' => $activity->id,
                    'result' => ['crm_activity_id' => $activity->id],
                ]);

                return $activity;
            });
        }, 3);
    }

    private function normalize(array $item): array
    {
        $type = Arr::get($item, 'type');
        $priority = Arr::get($item, 'priority');

        return [
            'title' => mb_substr(trim((string) Arr::get($item, 'title', 'AI suggested action')), 0, 255),
            'type' => in_array($type, ['task', 'visit', 'reminder'], true) ? $type : 'task',
            'priority' => in_array($priority, ['low', 'normal', 'high'], true) ? $priority : 'normal',
            'reason' => trim((string) Arr::get($item, 'reason', 'Suggested by AI briefing.')),
            'due_in_days' => min(max((int) Arr::get($item, 'due_in_days', 3), 0), 90),
        ];
    }
}
