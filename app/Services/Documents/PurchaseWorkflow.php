<?php

namespace App\Services\Documents;

use App\Models\FiscalYear;
use App\Models\PurchaseDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseWorkflow
{
    public function correctOrderDates(PurchaseDocument $document, User $user, array $data): void
    {
        abort_unless($user->hasRole('admin'), 403);

        DB::transaction(function () use ($document, $user, $data): void {
            $document = PurchaseDocument::lockForUpdate()->findOrFail($document->id);
            abort_unless($document->type === 'purchase_order', 422);
            if (! in_array($document->status, ['ordered', 'pending_approval'], true)
                || $document->receipts()->exists() || $document->returns()->exists()
                || $document->derivedDocuments()->exists() || $document->payments()->exists()) {
                throw ValidationException::withMessages(['issue_date' => __('Only purchase orders without receipts, invoices or payments can have their dates corrected.')]);
            }

            $before = ['issue_date' => $document->issue_date->toDateString(), 'expected_delivery_date' => $document->expected_delivery_date?->toDateString(), 'number' => $document->number];
            $after = ['issue_date' => $data['issue_date'], 'expected_delivery_date' => $data['expected_delivery_date'] ?? null];
            if ($before['issue_date'] === $after['issue_date'] && $before['expected_delivery_date'] === $after['expected_delivery_date']) {
                return;
            }

            $years = FiscalYear::query()->where(function ($query) use ($before, $after): void {
                foreach ([$before['issue_date'], $after['issue_date']] as $date) {
                    $query->orWhere(fn ($period) => $period->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date));
                }
            })->orderBy('id')->lockForUpdate()->get();
            $originalYear = $years->first(fn ($year) => $year->starts_on->toDateString() <= $before['issue_date'] && $year->ends_on->toDateString() >= $before['issue_date']);
            $targetYear = $years->first(fn ($year) => $year->starts_on->toDateString() <= $after['issue_date'] && $year->ends_on->toDateString() >= $after['issue_date']);
            if (! $originalYear || ! $targetYear) {
                throw ValidationException::withMessages(['issue_date' => __('No fiscal year exists for this date.')]);
            }
            if ($originalYear->status !== 'open' || $targetYear->status !== 'open') {
                throw ValidationException::withMessages(['issue_date' => __('The fiscal year is closed.')]);
            }
            if ($originalYear->id !== $targetYear->id) {
                if ($document->sent_at) {
                    throw ValidationException::withMessages(['issue_date' => __('A sent purchase order cannot be moved to another fiscal year.')]);
                }
                $issued = app(DocumentNumber::class)->next('purchase_order', $after['issue_date']);
                $after['number'] = $issued['number'];
                $after['document_series_id'] = $issued['series']->id;
            }
            $document->update($after);
            $this->record($document, 'dates_corrected', $user->id, $document->status, $document->status, [
                'reason' => $data['reason'],
                'previous_issue_date' => $before['issue_date'], 'issue_date' => $document->issue_date->toDateString(),
                'previous_expected_delivery_date' => $before['expected_delivery_date'], 'expected_delivery_date' => $document->expected_delivery_date?->toDateString(),
                'previous_number' => $before['number'], 'number' => $document->number,
            ]);
        });
    }

    public function record(PurchaseDocument $document, string $event, ?int $userId, ?string $fromStatus = null, ?string $toStatus = null, array $metadata = []): void
    {
        $document->events()->create([
            'user_id' => $userId,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }

    public function markSent(PurchaseDocument $document, User $user, string $recipient): PurchaseDocument
    {
        return DB::transaction(function () use ($document, $user, $recipient): PurchaseDocument {
            $document = PurchaseDocument::lockForUpdate()->findOrFail($document->id);
            $allowed = $document->type === 'purchase_rfq'
                ? ['draft', 'sent', 'quoted']
                : ['ordered', 'partially_received', 'received'];
            abort_unless(in_array($document->status, $allowed, true), 422);
            $from = $document->status;
            $to = $document->type === 'purchase_rfq' && $from === 'draft' ? 'sent' : $from;
            $document->update(['status' => $to, 'sent_at' => now()]);
            $this->record($document, 'sent', $user->id, $from, $to, ['recipient' => $recipient]);

            return $document;
        });
    }

    public function cancel(PurchaseDocument $document, User $user, string $reason): PurchaseDocument
    {
        return DB::transaction(function () use ($document, $user, $reason): PurchaseDocument {
            $document = PurchaseDocument::lockForUpdate()->findOrFail($document->id);
            abort_unless(in_array($document->type, ['purchase_rfq', 'purchase_order'], true), 422);
            abort_if(in_array($document->status, ['cancelled', 'closed', 'accepted', 'rejected'], true), 422);
            if ($document->type === 'purchase_order') {
                $hasActivity = $document->receipts()->exists()
                    || $document->derivedDocuments()->where('type', 'supplier_invoice')->exists();
                if ($hasActivity) {
                    throw ValidationException::withMessages(['reason' => __('Use close when an order already has receipts or invoices.')]);
                }
            }
            $from = $document->status;
            $document->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
                'locked_at' => now(),
            ]);
            $this->record($document, 'cancelled', $user->id, $from, 'cancelled', ['reason' => $reason]);

            return $document;
        });
    }

    public function close(PurchaseDocument $document, User $user, string $reason): PurchaseDocument
    {
        return DB::transaction(function () use ($document, $user, $reason): PurchaseDocument {
            $document = PurchaseDocument::lockForUpdate()->findOrFail($document->id);
            abort_unless($document->type === 'purchase_order' && in_array($document->status, ['ordered', 'partially_received', 'received'], true), 422);
            $from = $document->status;
            $document->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $user->id,
                'closing_reason' => $reason,
                'locked_at' => now(),
            ]);
            $this->record($document, 'closed', $user->id, $from, 'closed', ['reason' => $reason, 'previous_status' => $from]);

            return $document;
        });
    }

    public function reopen(PurchaseDocument $document, User $user): PurchaseDocument
    {
        return DB::transaction(function () use ($document, $user): PurchaseDocument {
            $document = PurchaseDocument::lockForUpdate()->with('lines.product')->findOrFail($document->id);
            abort_unless($document->type === 'purchase_order' && $document->status === 'closed', 422);
            $received = $document->lines->contains(fn ($line) => (float) $line->received_quantity > 0);
            $complete = $document->lines->every(fn ($line) => ! $line->product?->track_stock || (float) $line->received_quantity >= (float) $line->quantity);
            $to = $complete ? 'received' : ($received ? 'partially_received' : 'ordered');
            $document->update(['status' => $to, 'closed_at' => null, 'closed_by' => null, 'closing_reason' => null]);
            $this->record($document, 'reopened', $user->id, 'closed', $to);

            return $document;
        });
    }
}
