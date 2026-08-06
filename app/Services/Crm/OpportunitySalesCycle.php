<?php

namespace App\Services\Crm;

use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Arr;

class OpportunitySalesCycle
{
    private const CLOSED_STAGES = ['won', 'lost'];

    public function prepareActiveData(array $data, User $actor): array
    {
        if (in_array($data['stage'], self::CLOSED_STAGES, true)) {
            return $data;
        }

        $data['owner_user_id'] ??= $actor->hasRole('admin', 'commercial')
            ? $actor->id
            : User::query()->whereIn('role', ['admin', 'commercial'])->orderBy('id')->value('id');
        $data['expected_close_on'] ??= today()->addDays(30)->toDateString();

        return $data;
    }

    public function ensureNextAction(Opportunity $opportunity, ?User $actor = null): void
    {
        if (in_array($opportunity->stage, self::CLOSED_STAGES, true) || $opportunity->nextActivity()->exists()) {
            return;
        }

        $dueAt = now()->addDay();
        while ($dueAt->isWeekend()) {
            $dueAt = $dueAt->addDay();
        }

        CrmActivity::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $opportunity->person_id,
            'installation_id' => $opportunity->installation_id,
            'assigned_user_id' => $opportunity->owner_user_id,
            'created_by' => $actor?->id,
            'type' => 'task',
            'title' => __('Review opportunity and define next step'),
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => $dueAt->setTime(9, 0),
        ]);
    }

    public function refreshScore(Opportunity $opportunity): int
    {
        $opportunity->loadMissing('customer', 'nextActivity');
        $weights = config('crm.scoring');
        $facts = [
            'customer_contactable' => ! $opportunity->customer?->do_not_contact
                && filled($opportunity->customer?->email ?: $opportunity->customer?->phone),
            'source_identified' => filled($opportunity->source),
            'owner_assigned' => filled($opportunity->owner_user_id),
            'expected_close_set' => filled($opportunity->expected_close_on),
            'next_action_scheduled' => filled($opportunity->nextActivity),
            'estimated_value_set' => (float) $opportunity->estimated_value > 0,
            'advanced_stage' => in_array($opportunity->stage, ['survey', 'proposal', 'negotiation', 'won'], true),
        ];
        $score = (int) collect($facts)->sum(fn (bool $met, string $key) => $met ? (int) Arr::get($weights, $key, 0) : 0);
        $opportunity->updateQuietly(['commercial_score' => min(100, $score)]);

        return min(100, $score);
    }
}
