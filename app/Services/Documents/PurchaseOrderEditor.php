<?php

namespace App\Services\Documents;

use App\Models\AuditLog;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderEditor
{
    public function eligible(PurchaseDocument $order): bool
    {
        return $order->type === 'purchase_order' && in_array($order->status, ['ordered', 'pending_approval'], true)
            && ! $order->source_document_id && ! $order->sales_document_id && ! $order->planning_scenario_request_id
            && ! $order->procurement_batch_id && ! $order->installation_id && ! $order->work_order_id
            && (float) $order->paid_amount === 0.0
            && ! $order->receipts()->exists() && ! $order->returns()->exists() && ! $order->payments()->exists()
            && ! $order->qualityIncidents()->exists() && ! $order->derivedDocuments()->exists() && ! $order->accountingEntry()->exists()
            && ! $order->lines()->where(fn ($q) => $q->where('received_quantity', '>', 0)->orWhere('returned_quantity', '>', 0)->orWhereNotNull('source_line_id'))->exists();
    }

    public function snapshot(PurchaseDocument $order): array
    {
        return [...$order->attributesToArray(), 'lines' => $order->lines()->get()->toArray(), 'approvals' => $order->orderApprovals()->get()->toArray()];
    }

    public function revision(PurchaseDocument $order): string
    {
        return hash('sha256', json_encode($this->snapshot($order), JSON_THROW_ON_ERROR));
    }

    public function update(PurchaseDocument $order, array $data, array $calculation, string $revision, string $reason, int $userId): void
    {
        DB::transaction(function () use ($order, $data, $calculation, $revision, $reason, $userId) {
            $order = PurchaseDocument::lockForUpdate()->findOrFail($order->id);
            if (! $this->eligible($order)) {
                throw ValidationException::withMessages(['reason' => __('Only direct purchase orders without receipts, invoices or payments can be edited.')]);
            }
            if (! hash_equals($this->revision($order), $revision)) {
                throw ValidationException::withMessages(['reason' => __('The purchase order changed. Reload it before editing.')]);
            }
            $before = $this->snapshot($order);
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $order->orderApprovals()->whereIn('status', ['pending', 'approved'])->update(['status' => 'cancelled', 'decided_by' => $userId, 'decided_at' => now(), 'notes' => __('Cancelled because the purchase order was edited.')]);
            $order->update([...collect($data)->only(['supplier_id', 'issue_date', 'expected_delivery_date', 'notes'])->all(), ...$calculation['totals'],
                'supplier_snapshot' => $supplier->purchaseSnapshot(), 'status' => 'ordered', 'locked_at' => now(), 'approved_at' => null, 'approved_by' => null, 'approval_notes' => null,
                'sent_at' => null, 'supplier_confirmed_at' => null, 'supplier_confirmed_by' => null, 'supplier_confirmed_delivery_date' => null, 'supplier_confirmation_reference' => null]);
            $order->lines()->delete();
            $order->lines()->createMany($calculation['lines']);
            app(PurchaseOrderApprovalWorkflow::class)->request($order->refresh(), $userId);
            $after = $this->snapshot($order->refresh());
            AuditLog::create(['user_id' => $userId, 'event' => 'purchase_order_edited', 'auditable_type' => $order->getMorphClass(), 'auditable_id' => $order->id, 'old_values' => $before, 'new_values' => [...$after, 'reason' => $reason]]);
            app(PurchaseWorkflow::class)->record($order, 'order_edited', $userId, $before['status'], $order->status, ['reason' => $reason, 'previous_supplier_id' => $before['supplier_id'], 'supplier_id' => $order->supplier_id, 'previous_amount' => $before['amount'], 'amount' => $order->amount]);
        });
    }
}
