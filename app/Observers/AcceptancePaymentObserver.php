<?php

namespace App\Observers;

use App\Models\SalesDocumentPayment;
use App\Services\Finance\AcceptancePaymentManager;

class AcceptancePaymentObserver
{
    public function created(SalesDocumentPayment $payment): void
    {
        $this->refresh($payment);
    }

    public function deleted(SalesDocumentPayment $payment): void
    {
        $this->refresh($payment);
    }

    private function refresh(SalesDocumentPayment $payment): void
    {
        $quote = $payment->document?->quote;
        if ($quote && $payment->document->is_acceptance_invoice) {
            app(AcceptancePaymentManager::class)->refresh($quote);
        }
    }
}
