<?php

namespace App\Observers;

use App\Models\BankReconciliation;
use App\Models\PaymentBatchItem;

class BankReconciliationFinancialObserver
{
    public function created(BankReconciliation $reconciliation): void
    {
        $this->synchronizeBatch($reconciliation->supplier_payment_id);
    }

    public function deleted(BankReconciliation $reconciliation): void
    {
        $this->synchronizeBatch($reconciliation->supplier_payment_id);
    }

    private function synchronizeBatch(?int $supplierPaymentId): void
    {
        if (! $supplierPaymentId) {
            return;
        }

        $item = PaymentBatchItem::with('batch.items.supplierPayment.reconciliation')
            ->where('supplier_payment_id', $supplierPaymentId)
            ->first();
        $batch = $item?->batch;
        if (! $batch || ! in_array($batch->status, ['paid', 'reconciled'], true)) {
            return;
        }

        $allReconciled = $batch->items->isNotEmpty()
            && $batch->items->every(fn (PaymentBatchItem $batchItem) => $batchItem->supplier_payment_id && $batchItem->supplierPayment?->reconciliation);

        if ($allReconciled && $batch->status !== 'reconciled') {
            $batch->update(['status' => 'reconciled', 'reconciled_at' => now()]);
        } elseif (! $allReconciled && $batch->status === 'reconciled') {
            $batch->update(['status' => 'paid', 'reconciled_at' => null]);
        }
    }
}
