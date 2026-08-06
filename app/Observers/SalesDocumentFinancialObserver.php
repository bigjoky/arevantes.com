<?php

namespace App\Observers;

use App\Models\SalesDocument;

class SalesDocumentFinancialObserver
{
    public function updated(SalesDocument $document): void
    {
        if (! $document->wasChanged('status') || $document->type !== 'invoice') {
            return;
        }

        $action = $document->collectionAction()->first();
        if (! $action) {
            return;
        }

        if ($document->status === 'paid') {
            if ($action->status !== 'resolved') {
                $action->update([
                    'status' => 'resolved',
                    'resolution_reason' => 'payment_received',
                    'resolved_at' => now(),
                    'next_action_on' => null,
                ]);
            }

            return;
        }

        if ($document->getOriginal('status') === 'paid' && $action->status === 'resolved' && $action->resolution_reason === 'payment_received') {
            $action->update([
                'status' => 'open',
                'resolution_reason' => null,
                'resolved_at' => null,
                'next_action_on' => today(),
            ]);
        }
    }
}
