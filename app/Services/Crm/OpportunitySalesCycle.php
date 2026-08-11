<?php

namespace App\Services\Crm;

use App\Models\CrmActivity;
use App\Models\CrmSetting;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OpportunitySalesCycle
{
    private ?CrmSetting $settings = null;

    private const CLOSED_STAGES = ['won', 'lost'];

    private const TRANSITIONS = [
        'new' => ['qualified', 'proposal', 'lost'],
        'qualified' => ['new', 'survey', 'proposal', 'won', 'lost'],
        'survey' => ['qualified', 'proposal', 'won', 'lost'],
        'proposal' => ['qualified', 'survey', 'negotiation', 'won', 'lost'],
        'negotiation' => ['proposal', 'won', 'lost'],
        'won' => [],
        'lost' => [],
    ];

    public function prepareActiveData(array $data, User $actor): array
    {
        if ($data['stage'] === 'won') {
            $data['probability'] = 100;

            return $data;
        }

        if ($data['stage'] === 'lost') {
            $data['probability'] = 0;

            return $data;
        }

        $data['owner_user_id'] ??= $actor->hasRole('admin', 'commercial')
            ? $actor->id
            : User::query()->whereIn('role', ['admin', 'commercial'])->orderBy('id')->value('id');
        $data['expected_close_on'] ??= today()->addDays(30)->toDateString();

        return $data;
    }

    public function assertCanTransition(Opportunity $opportunity, string $stage): void
    {
        if ($opportunity->stage === $stage) {
            return;
        }

        if (! in_array($stage, self::TRANSITIONS[$opportunity->stage] ?? [], true)) {
            throw ValidationException::withMessages([
                'stage' => __('The opportunity cannot move from :from to :to.', [
                    'from' => $opportunity->stage,
                    'to' => $stage,
                ]),
            ]);
        }
    }

    /** @return array<int, string> */
    public function availableTransitions(Opportunity $opportunity): array
    {
        return [$opportunity->stage, ...(self::TRANSITIONS[$opportunity->stage] ?? [])];
    }

    public function synchronizeStage(Opportunity $opportunity, ?User $actor = null): void
    {
        if (in_array($opportunity->stage, self::CLOSED_STAGES, true)) {
            $opportunity->activities()
                ->whereIn('status', ['pending', 'in_progress'])
                ->update([
                    'status' => 'cancelled',
                    'blocked_reason' => __('Opportunity closed as :stage.', ['stage' => $opportunity->stage]),
                    'updated_at' => now(),
                ]);
            $opportunity->unsetRelation('nextActivity');

            return;
        }

        $this->ensureNextAction($opportunity, $actor);
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
        $score = (int) collect($this->scoreBreakdown($opportunity))->sum('points');
        $opportunity->updateQuietly(['commercial_score' => min(100, $score)]);

        return min(100, $score);
    }

    /** @return array<string, array{met: bool, points: int, weight: int}> */
    public function scoreBreakdown(Opportunity $opportunity): array
    {
        $opportunity->loadMissing('customer', 'nextActivity');
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

        $weights = $this->settings()->scoringWeights();

        return collect($facts)->mapWithKeys(fn (bool $met, string $key) => [
            $key => [
                'met' => $met,
                'points' => $met ? ($weights[$key] ?? 0) : 0,
                'weight' => $weights[$key] ?? 0,
            ],
        ])->all();
    }

    public function forgetSettings(): void
    {
        $this->settings = null;
    }

    private function settings(): CrmSetting
    {
        return $this->settings ??= CrmSetting::current();
    }
}
