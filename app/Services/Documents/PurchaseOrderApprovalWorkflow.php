<?php

namespace App\Services\Documents;

use App\Models\PurchaseApprovalRule;
use App\Models\PurchaseDocument;
use App\Models\PurchaseOrderApproval;
use App\Models\PurchaseSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseOrderApprovalWorkflow
{
    /** @return Collection<int, PurchaseApprovalRule|null> */
    public function requirements(PurchaseDocument $order): Collection
    {
        $productTypes = $order->lines()->join('products', 'products.id', '=', 'purchase_document_lines.product_id')->distinct()->pluck('products.type');
        $rules = PurchaseApprovalRule::query()
            ->active()
            ->where('min_amount', '<=', $order->amount)
            ->where(fn ($query) => $query->whereNull('max_amount')->orWhere('max_amount', '>=', $order->amount))
            ->where(fn ($query) => $query->whereNull('supplier_id')->orWhere('supplier_id', $order->supplier_id))
            ->where(fn ($query) => $query->whereNull('product_type')->orWhereIn('product_type', $productTypes))
            ->orderBy('priority')
            ->orderByDesc('min_amount')
            ->get();

        if ($rules->isEmpty() && (float) $order->amount >= (float) PurchaseSetting::current()->order_approval_threshold) {
            return collect([null]);
        }

        return $rules;
    }

    public function request(PurchaseDocument $order, int $userId): bool
    {
        $requirements = $this->requirements($order);
        if ($requirements->isEmpty()) {
            return false;
        }

        foreach ($requirements as $rule) {
            $order->orderApprovals()->create([
                'purchase_approval_rule_id' => $rule?->id,
                'requested_by' => $userId,
                'status' => 'pending',
                'reason' => $rule?->name ?: __('Default order amount threshold'),
                'requested_at' => now(),
            ]);
        }
        $order->update(['status' => 'pending_approval', 'locked_at' => null]);
        app(PurchaseWorkflow::class)->record($order, 'approval_requested', $userId, null, 'pending_approval', [
            'requirements' => $requirements->count(),
            'rules' => $requirements->filter()->pluck('id')->values()->all(),
        ]);

        return true;
    }

    public function decide(PurchaseDocument $order, User $user, string $decision, string $notes): PurchaseOrderApproval
    {
        $order = PurchaseDocument::lockForUpdate()->findOrFail($order->id);
        abort_unless($order->type === 'purchase_order' && $order->status === 'pending_approval', 422);
        abort_if($order->created_by === $user->id && ! $user->hasRole('admin'), 403, __('A different authorised user must decide this purchase order.'));

        $approval = $order->orderApprovals()->where('status', 'pending')->orderBy('id')->lockForUpdate()->first();
        if (! $approval) {
            throw ValidationException::withMessages(['approval_notes' => __('No pending approval requirement was found.')]);
        }

        $approval->update([
            'status' => $decision,
            'decided_by' => $user->id,
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        if ($decision === 'rejected') {
            $order->orderApprovals()
                ->where('status', 'pending')
                ->whereKeyNot($approval->id)
                ->update([
                    'status' => 'cancelled',
                    'decided_by' => $user->id,
                    'notes' => __('Cancelled after another approval requirement was rejected.'),
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);
            $order->update(['status' => 'approval_rejected', 'locked_at' => now(), 'approval_notes' => $notes]);
            app(PurchaseWorkflow::class)->record($order, 'purchase_order_rejected', $user->id, 'pending_approval', 'approval_rejected', ['approval_id' => $approval->id, 'notes' => $notes]);

            return $approval;
        }

        if (! $order->orderApprovals()->where('status', 'pending')->exists()) {
            $order->update([
                'status' => 'ordered',
                'approved_at' => now(),
                'approved_by' => $user->id,
                'approval_notes' => $notes,
                'locked_at' => now(),
            ]);
        }
        app(PurchaseWorkflow::class)->record($order, 'purchase_order_approved', $user->id, 'pending_approval', $order->fresh()->status, ['approval_id' => $approval->id, 'notes' => $notes]);

        return $approval;
    }
}
