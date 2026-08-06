<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Services\Finance\AcceptancePaymentManager;
use App\Services\Inventory\ProjectMaterialPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteStatusWorkflow
{
    public function __construct(
        private readonly ProjectMaterialPlanner $materials,
        private readonly AcceptancePaymentManager $acceptancePayments,
    ) {}

    public function apply(Quote $quote, string $status): void
    {
        if ($status !== 'accepted' && $quote->status === 'accepted' && $quote->acceptanceInvoice()->exists()) {
            throw ValidationException::withMessages([
                'status' => __('An accepted quote with an issued acceptance invoice cannot change status. Correct the invoice first.'),
            ]);
        }

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

            if ($status === 'accepted') {
                $this->acceptancePayments->activate($quote);
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
