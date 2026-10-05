<?php

namespace App\Services\Tax;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\PurchaseDocument;
use App\Models\PurchaseFiscalClassification;
use App\Models\TaxReturn;
use App\Services\Accounting\AccountingPeriodManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseFiscalReview
{
    public const CLASSES = ['pending', 'domestic_current', 'domestic_investment'];

    public function evidence(PurchaseDocument $purchaseDocument): array
    {
        $purchaseDocument->loadMissing('lines');

        return [
            'version' => 'purchase-fiscal-review-v1',
            'document' => $purchaseDocument->only(['type', 'number', 'supplier_number', 'supplier_snapshot',
                'issue_date', 'subtotal', 'tax_amount', 'shipping_cost', 'amount']),
            'lines' => $purchaseDocument->lines->map(fn ($line) => $line->only(['id', 'position', 'description',
                'product_id', 'quantity', 'unit_price', 'discount_percent', 'tax_percent', 'subtotal', 'tax_amount', 'total']))->values()->all(),
        ];
    }

    public function fingerprint(PurchaseDocument $purchaseDocument): string
    {
        return hash('sha256', json_encode($this->evidence($purchaseDocument), JSON_THROW_ON_ERROR));
    }

    public function revision(PurchaseDocument $purchaseDocument): string
    {
        $purchaseDocument->loadMissing('fiscalClassification');

        return hash('sha256', json_encode([$this->fingerprint($purchaseDocument), $purchaseDocument->fiscalClassification?->attributesToArray()], JSON_THROW_ON_ERROR));
    }

    /** Requires human documentary confirmation in addition to these structural checks. */
    public function issues(PurchaseDocument $purchaseDocument): array
    {
        return array_values(array_filter(app(PurchaseFiscalEvidence::class)->issues($purchaseDocument),
            fn ($issue) => $issue !== 'purchase_review_pending'));
    }

    public function isCurrent(PurchaseDocument $purchaseDocument): bool
    {
        $purchaseDocument->loadMissing('fiscalClassification');
        $review = $purchaseDocument->fiscalClassification;

        return $review && in_array($review->operation_class, ['domestic_current', 'domestic_investment'], true)
            && $this->issues($purchaseDocument) === [] && hash_equals($review->evidence_fingerprint, $this->fingerprint($purchaseDocument));
    }

    public function protectionIssues(PurchaseDocument $purchaseDocument): array
    {
        $date = $purchaseDocument->issue_date;
        if (! $date) {
            return ['The purchase invoice date must belong to an open fiscal year.'];
        }
        $issues = [];
        if (! FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->where('status', 'open')->exists()) {
            $issues[] = 'The purchase invoice date must belong to an open fiscal year.';
        }
        if (AccountingPeriod::where('year', $date->year)->where('month', $date->month)->where('status', 'closed')->exists()) {
            $issues[] = 'The accounting period is closed.';
        }
        if (TaxReturn::where('tax_type', 'vat')->whereIn('status', ['prepared', 'filed'])
            ->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date)->exists()) {
            $issues[] = 'A prepared or filed tax return protects this purchase invoice.';
        }

        return $issues;
    }

    public function save(PurchaseDocument $purchaseDocument, array $data, Request $request): void
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-tax'), 403);
        abort_unless(in_array($data['operation_class'], self::CLASSES, true), 422);
        DB::transaction(function () use ($purchaseDocument, $data, $request) {
            $purchaseDocument = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            $purchaseDocument->load(['lines', 'fiscalClassification']);
            if (! hash_equals($this->revision($purchaseDocument), $data['revision'])) {
                $this->fail('The fiscal evidence or classification has changed. Reload before reviewing.');
            }
            if (! $purchaseDocument->issue_date) {
                $this->fail('The purchase invoice date must belong to an open fiscal year.');
            }
            if (! filled($data['source_reference'] ?? null)) {
                $this->fail('A documentary review reference is required.');
            }
            $date = $purchaseDocument->issue_date->toDateString();
            $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
            if (! $year || $year->status !== 'open') {
                $this->fail('The purchase invoice date must belong to an open fiscal year.');
            }
            app(AccountingPeriodManager::class)->assertOpen($date);
            // The year lock serializes with settlement posting and monthly closure.
            // Do not lock the return after the year: posting locks these in the opposite order.
            if (TaxReturn::where('tax_type', 'vat')->whereIn('status', ['prepared', 'filed'])
                ->whereDate('period_from', '<=', $date)->whereDate('period_to', '>=', $date)->exists()) {
                $this->fail('A prepared or filed tax return protects this purchase invoice.');
            }
            if ($data['operation_class'] !== 'pending') {
                if (! ($data['confirmed'] ?? false)) {
                    $this->fail('Confirm fiscal territory, invoice class and deductible VAT before saving.');
                }
                if ($this->issues($purchaseDocument) !== []) {
                    $this->fail('The purchase invoice evidence must be completed before classification.');
                }
            }
            $before = $purchaseDocument->fiscalClassification?->attributesToArray();
            $review = PurchaseFiscalClassification::updateOrCreate(['purchase_document_id' => $purchaseDocument->id], [
                'operation_class' => $data['operation_class'], 'evidence_fingerprint' => $this->fingerprint($purchaseDocument),
                'evidence_snapshot' => [...$this->evidence($purchaseDocument), 'reviewer' => $request->user()->only(['id', 'name']), 'confirmation' => [
                    'domestic_vat_without_cash_accounting' => $data['operation_class'] !== 'pending',
                    'single_invoice_class' => $data['operation_class'] !== 'pending',
                    'deduction_in_invoice_period' => $data['operation_class'] !== 'pending',
                    'documentary_invoice_checked' => $data['operation_class'] !== 'pending',
                    'eur_general_regime_full_deduction' => $data['operation_class'] !== 'pending',
                ]], 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'reason' => $data['reason'], 'source_reference' => $data['source_reference'],
            ]);
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'purchase_fiscal_classified',
                'auditable_type' => $purchaseDocument->getMorphClass(), 'auditable_id' => $purchaseDocument->id,
                'old_values' => $before, 'new_values' => $review->attributesToArray(),
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(), 'method' => $request->method()]);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['operation_class' => __($message)]);
    }
}
