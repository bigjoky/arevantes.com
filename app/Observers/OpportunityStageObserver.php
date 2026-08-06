<?php

namespace App\Observers;

use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;

class OpportunityStageObserver
{
    public function created(Opportunity $opportunity): void
    {
        $this->record($opportunity, null, $opportunity->stage);
    }

    public function updated(Opportunity $opportunity): void
    {
        if (! $opportunity->wasChanged('stage')) {
            return;
        }

        $this->record(
            $opportunity,
            $opportunity->getOriginal('stage'),
            $opportunity->stage,
        );
    }

    private function record(Opportunity $opportunity, ?string $from, string $to): void
    {
        OpportunityStageHistory::create([
            'opportunity_id' => $opportunity->id,
            'changed_by' => auth()->id() ?? $opportunity->created_by,
            'from_stage' => $from,
            'to_stage' => $to,
            'changed_at' => now(),
        ]);
    }
}
