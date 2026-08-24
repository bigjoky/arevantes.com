<?php

namespace App\Observers;

use App\Models\CrmActivity;
use App\Models\OpportunityEvent;
use Illuminate\Support\Facades\Schema;

class OpportunityActivityObserver
{
    public function updated(CrmActivity $activity): void
    {
        if (! $activity->opportunity_id || ! Schema::hasTable('opportunity_events')) {
            return;
        }

        $changes = collect($activity->getChanges())
            ->except(['updated_at'])
            ->mapWithKeys(fn ($value, string $key) => [$key => [
                'from' => $activity->getOriginal($key),
                'to' => $value,
            ]])
            ->all();
        if ($changes === []) {
            return;
        }

        OpportunityEvent::create([
            'opportunity_id' => $activity->opportunity_id,
            'actor_id' => auth()->id(),
            'event_type' => 'activity_updated',
            'summary' => __('Activity updated: :title', ['title' => $activity->title]),
            'changes' => $changes,
            'occurred_at' => now(),
        ]);
    }
}
