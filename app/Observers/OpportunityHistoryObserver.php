<?php

namespace App\Observers;

use App\Models\Opportunity;
use App\Models\OpportunityEvent;
use App\Models\OpportunityStageHistory;
use Illuminate\Support\Facades\Schema;

class OpportunityHistoryObserver
{
    private const IGNORED_FIELDS = [
        'updated_at',
        'updated_by',
        'commercial_score',
        'first_response_sla_alerted_at',
        'first_response_sla_escalated_at',
        'code',
    ];

    private const STAGE_FIELDS = ['stage', 'probability', 'lost_reason', 'lost_reason_detail'];

    public function created(Opportunity $opportunity): void
    {
        $this->recordStage($opportunity, null, $opportunity->stage);
    }

    public function updated(Opportunity $opportunity): void
    {
        if ($opportunity->wasChanged('stage')) {
            $this->recordStage($opportunity, $opportunity->getOriginal('stage'), $opportunity->stage);
        }

        if (! Schema::hasTable('opportunity_events')) {
            return;
        }

        $changes = collect($opportunity->getChanges())
            ->except([...self::IGNORED_FIELDS, ...self::STAGE_FIELDS])
            ->mapWithKeys(fn ($value, string $key) => [$key => [
                'from' => $opportunity->getOriginal($key),
                'to' => $value,
            ]])
            ->all();
        if ($changes === []) {
            return;
        }

        OpportunityEvent::create([
            'opportunity_id' => $opportunity->id,
            'actor_id' => $opportunity->stageChangeActorId ?? auth()->id(),
            'event_type' => 'details_updated',
            'summary' => __('Opportunity details updated'),
            'note' => $opportunity->stageChangeReason,
            'changes' => $changes,
            'occurred_at' => now(),
        ]);
    }

    public function deleted(Opportunity $opportunity): void
    {
        $this->recordLifecycle($opportunity, 'archived', __('Opportunity archived'));
    }

    public function restored(Opportunity $opportunity): void
    {
        $this->recordLifecycle($opportunity, 'restored', __('Opportunity restored'));
    }

    private function recordStage(Opportunity $opportunity, ?string $from, string $to): void
    {
        OpportunityStageHistory::create([
            'opportunity_id' => $opportunity->id,
            'changed_by' => $from === null
                ? (auth()->id() ?? $opportunity->created_by)
                : ($opportunity->stageChangeActorId ?? auth()->id()),
            'from_stage' => $from,
            'to_stage' => $to,
            'reason' => $opportunity->stageChangeReason,
            'changed_at' => now(),
        ]);
    }

    private function recordLifecycle(Opportunity $opportunity, string $type, string $summary): void
    {
        if (! Schema::hasTable('opportunity_events')) {
            return;
        }

        OpportunityEvent::create([
            'opportunity_id' => $opportunity->id,
            'actor_id' => auth()->id(),
            'event_type' => $type,
            'summary' => $summary,
            'occurred_at' => now(),
        ]);
    }
}
