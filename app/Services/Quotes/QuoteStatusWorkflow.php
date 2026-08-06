<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Services\Inventory\ProjectMaterialPlanner;
use Illuminate\Support\Facades\DB;

class QuoteStatusWorkflow
{
    public function __construct(private readonly ProjectMaterialPlanner $materials) {}

    public function apply(Quote $quote, string $status): void
    {
        DB::transaction(function () use ($quote, $status): void {
            $quote->update([
                'status' => $status,
                'sent_at' => $status === 'sent' && ! $quote->sent_at ? now() : $quote->sent_at,
                'accepted_at' => $status === 'accepted' ? now() : null,
            ]);

            if ($status === 'accepted' && $quote->installation) {
                $quote->installation->update(['status' => 'approved', 'estimated_budget' => $quote->amount]);
                $this->materials->syncFromQuote($quote, $quote->installation);
            }

            if (! $quote->opportunity) {
                return;
            }

            if ($status === 'accepted') {
                $quote->opportunity->update(['stage' => 'won', 'probability' => 100, 'lost_reason' => null, 'lost_reason_detail' => null]);
            } elseif ($status === 'rejected') {
                $quote->opportunity->update([
                    'stage' => 'lost',
                    'probability' => 0,
                    'lost_reason' => 'other',
                    'lost_reason_detail' => __('Quote :number rejected.', ['number' => $quote->number]),
                ]);
            }
        });
    }
}
