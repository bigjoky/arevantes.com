<?php

namespace App\Observers;

use App\Models\CollectionAction;

class CollectionActionHistoryObserver
{
    public function created(CollectionAction $action): void
    {
        $this->record($action, null);
    }

    public function updated(CollectionAction $action): void
    {
        $tracked = ['status', 'assigned_to', 'contacted_on', 'promised_on', 'promised_amount', 'next_action_on', 'resolved_at', 'notes'];
        if (! collect($tracked)->contains(fn (string $attribute) => $action->wasChanged($attribute))) {
            return;
        }

        $this->record($action, $action->getOriginal('status'));
    }

    private function record(CollectionAction $action, ?string $statusFrom): void
    {
        $eventType = match (true) {
            $action->status === 'promised' && $statusFrom !== 'promised' => 'promise',
            $action->status === 'disputed' && $statusFrom !== 'disputed' => 'dispute',
            $action->status === 'resolved' && $statusFrom !== 'resolved' => 'resolved',
            $action->status === 'open' && $statusFrom === 'resolved' => 'reopened',
            $action->wasChanged('contacted_on') => 'contact',
            $action->wasChanged('notes') && ! $action->wasChanged('status') => 'note',
            default => 'updated',
        };

        $action->events()->create([
            'event_type' => $eventType,
            'status_from' => $statusFrom,
            'status_to' => $action->status,
            'promised_on' => $action->promised_on,
            'promised_amount' => $action->promised_amount,
            'next_action_on' => $action->next_action_on,
            'notes' => $action->notes,
            'occurred_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }
}
