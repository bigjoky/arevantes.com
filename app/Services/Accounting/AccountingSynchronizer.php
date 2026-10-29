<?php

namespace App\Services\Accounting;

use App\Models\AccountingConfiguration;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use App\Services\Finance\ReceivedInvoiceIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingSynchronizer
{
    public function __construct(private AccountingPeriodManager $periods) {}

    private array $taxAccounts = [];

    private array $decisions = [];

    public function sync(?int $userId = null): int
    {
        $created = 0;
        foreach (SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNot('status', 'draft')->lazyById() as $document) {
            $created += $this->salesDocument($document, $userId);
        }
        foreach (PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereNotIn('status', ['draft', 'review_required'])
            ->where(fn ($query) => $query->whereNull('matching_status')->orWhereNot('matching_status', 'review_required'))
            ->lazyById() as $document) {
            $created += $this->purchaseDocument($document, $userId);
        }
        foreach (Expense::lazyById() as $expense) {
            $created += $this->expense($expense, $userId);
        }
        foreach (SalesDocumentPayment::lazyById() as $payment) {
            $created += $this->customerPayment($payment, $userId);
        }
        foreach (SupplierPayment::lazyById() as $payment) {
            $created += $this->supplierPayment($payment, $userId);
        }

        return $created;
    }

    public function syncSalesDocument(SalesDocument $document, ?int $userId = null): int
    {
        return $this->salesDocument($document, $userId);
    }

    public function syncPurchaseDocument(PurchaseDocument $document, ?int $userId = null): int
    {
        if ($document->status === 'review_required' || $document->matching_status === 'review_required') {
            return 0;
        }

        app(ReceivedInvoiceIdentity::class)->assertUsable($document);

        return $this->purchaseDocument($document, $userId);
    }

    public function syncExpense(Expense $expense, ?int $userId = null, ?array $controlDecision = null): int
    {
        return $this->expense($expense, $userId, $controlDecision);
    }

    public function syncCustomerPayment(SalesDocumentPayment $payment, ?int $userId = null): int
    {
        return $this->customerPayment($payment, $userId);
    }

    public function allocateCollectedPayments(SalesDocument $document, ?int $userId): void
    {
        if (! $document->collect_before_invoice) {
            return;
        }
        $account = $this->frozen($document, ['430'], 'receivable', asOf: $document->issue_date->toDateString()) ?: $this->account('receivable', $document, $document->customer, $document->customer?->receivable_account_code, '430');
        foreach ($document->payments()->get() as $payment) {
            $this->post($payment, 'invoice_allocation', $document->issue_date->toDateString(), $document->number, $userId, [[$this->frozen($payment, ['438'], 'customer_advance', 'payment', $document->issue_date->toDateString()) ?: $this->account('customer_advance', $payment, $document->customer, null, '438'), $payment->amount, 0], [$account, 0, $payment->amount]]);
        }
    }

    public function syncSupplierPayment(SupplierPayment $payment, ?int $userId = null): int
    {
        return $this->supplierPayment($payment, $userId);
    }

    private function salesDocument(SalesDocument $document, ?int $userId): int
    {
        return DB::transaction(function () use ($document, $userId) {
            $document = SalesDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (JournalEntry::whereMorphedTo('source', $document)->where('event', 'document')->exists()) {
                return 0;
            }
            $this->decisions = [];
            $this->taxAccounts = [];

            return $this->post($document, 'document', $document->issue_date->toDateString(), $document->number, $userId, $this->salesDocumentLines($document));
        });
    }

    public function salesDocumentLines(SalesDocument $document): array
    {
        $creditNote = $document->type === 'credit_note';
        $document->loadMissing(['lines.product', 'customer']);
        $customerAccount = $this->account('receivable', $document, $document->customer, $document->customer?->receivable_account_code, '430');
        $revenue = $document->lines->groupBy(fn ($line) => $this->account('revenue', $document, $line->product, $line->product?->sale_account_code, '700'))->map(fn ($lines) => round((float) $lines->sum(fn ($line) => (float) $line->subtotal - (float) $line->discount_amount), 2));
        if ($revenue->isEmpty()) {
            $revenue = collect([$this->account('revenue', $document, null, null, '700') => abs((float) $document->subtotal - (float) $document->discount_amount)]);
        }
        $lines = [[$customerAccount, $creditNote ? 0 : abs((float) $document->amount), $creditNote ? abs((float) $document->amount) : 0]];
        foreach ($revenue as $code => $amount) {
            $amount = abs((float) $amount);
            $lines[] = [$code, $creditNote ? $amount : 0, $creditNote ? 0 : $amount];
        }
        $taxes = $document->lines->groupBy(fn ($line) => (string) $line->tax_percent)->map(fn ($items) => round(abs((float) $items->sum('tax_amount')), 2));
        if ($taxes->isEmpty() && abs((float) $document->tax_amount) > 0) {
            $taxes = collect(['default' => abs((float) $document->tax_amount)]);
        }
        foreach ($taxes as $rate => $amount) {
            if (abs((float) $amount) < 0.005) {
                continue;
            }
            $lines[] = [$this->taxAccount($rate === 'default' ? null : (float) $rate, 'output', $document), $creditNote ? $amount : 0, $creditNote ? 0 : $amount];
        }

        return $lines;
    }

    private function purchaseDocument(PurchaseDocument $document, ?int $userId): int
    {
        return DB::transaction(function () use ($document, $userId) {
            $document = PurchaseDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (JournalEntry::whereMorphedTo('source', $document)->where('event', 'document')->exists()) {
                return 0;
            }
            $this->decisions = [];
            $this->taxAccounts = [];

            app(ReceivedInvoiceIdentity::class)->assertUsable($document);
            $creditNote = $document->type === 'supplier_credit_note';
            $document->loadMissing(['lines.product', 'supplier']);
            $supplierAccount = $this->account('supplier', $document, $document->supplier, $document->supplier?->payable_account_code, '400');
            $costs = $document->lines->groupBy(fn ($line) => $this->account('purchase', $document, $line->product, $line->product?->purchase_account_code, '600'))->map(fn ($lines) => round((float) $lines->sum('subtotal'), 2));
            if ($costs->isEmpty()) {
                $costs = collect([$this->account('purchase', $document, null, null, '600') => (float) $document->subtotal - (float) $document->shipping_cost]);
            }
            if (abs((float) $document->shipping_cost) > 0.005) {
                $costs['624'] = round((float) ($costs['624'] ?? 0) + (float) $document->shipping_cost, 2);
            }
            $lines = [];
            foreach ($costs as $code => $amount) {
                $lines[] = [$code, $creditNote ? 0 : abs($amount), $creditNote ? abs($amount) : 0];
            }
            $taxes = $document->lines->groupBy(fn ($line) => (string) $line->tax_percent)->map(fn ($items) => round((float) $items->sum('tax_amount'), 2));
            $shippingTax = round((float) $document->tax_amount - (float) $document->lines->sum('tax_amount'), 2);
            if (abs($shippingTax) > 0.005) {
                $taxes['21'] = round((float) ($taxes['21'] ?? 0) + $shippingTax, 2);
            }
            if ($taxes->isEmpty() && abs((float) $document->tax_amount) > 0) {
                $taxes = collect(['default' => abs((float) $document->tax_amount)]);
            }
            foreach ($taxes as $rate => $amount) {
                if (abs((float) $amount) < 0.005) {
                    continue;
                }
                $lines[] = [$this->taxAccount($rate === 'default' ? null : (float) $rate, 'input', $document), $creditNote ? 0 : abs($amount), $creditNote ? abs($amount) : 0];
            }
            $lines[] = [$supplierAccount, $creditNote ? abs((float) $document->amount) : 0, $creditNote ? 0 : abs((float) $document->amount)];

            return $this->post($document, 'document', $document->issue_date->toDateString(), $document->number, $userId, $lines);
        });
    }

    private function expense(Expense $expense, ?int $userId, ?array $controlDecision = null): int
    {
        return DB::transaction(function () use ($expense, $userId, $controlDecision) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if (JournalEntry::whereMorphedTo('source', $expense)->where('event', 'document')->exists()) {
                return 0;
            }
            $this->decisions = [];
            $this->taxAccounts = [];

            app(ReceivedInvoiceIdentity::class)->assertUsable($expense);

            $lines = $this->expenseLines($expense);
            if ($controlDecision) {
                $this->decisions = array_values(array_filter($this->decisions, fn ($d) => ! in_array($d['role'], ['supplier', 'creditor'])));
                $this->decisions[] = $controlDecision;
            }

            return $this->post($expense, 'document', $expense->expense_date->toDateString(), $expense->number, $userId, $lines);
        });
    }

    public function expenseLines(Expense $expense): array
    {
        $expense->loadMissing(['category', 'supplier', 'taxLines']);
        $legacy = $expense->creditor_account_code ?: $expense->supplier?->service_payable_account_code;
        $role = str_starts_with($legacy ?? '', '400') ? 'supplier' : 'creditor';
        $creditorAccount = $this->account($role, $expense, $expense->supplier, $legacy, $role === 'supplier' ? '400' : '410');
        $deductibleTax = round((float) $expense->taxLines->sum('deductible_amount'), 2);
        if ($expense->taxLines->isEmpty()) {
            $deductibleTax = (float) $expense->tax_amount;
        }
        $nonDeductibleTax = round((float) $expense->tax_amount - $deductibleTax, 2);
        $lines = [
            [$this->account('expense', $expense, null, null, '629', $expense->category, $expense->category?->expense_account_code), round((float) $expense->subtotal + $nonDeductibleTax, 2), 0],
        ];
        foreach ($expense->taxLines->where('deductible_amount', '>', 0)->groupBy(fn ($line) => (string) $line->tax_rate) as $rate => $taxLines) {
            $lines[] = [$this->taxAccount((float) $rate, 'input'), round((float) $taxLines->sum('deductible_amount'), 2), 0];
        }
        if ($expense->taxLines->isEmpty() && $deductibleTax > 0) {
            $lines[] = [$this->taxAccount((float) $expense->subtotal > 0 ? (float) $expense->tax_amount / (float) $expense->subtotal * 100 : null, 'input'), $deductibleTax, 0];
        }
        if ((float) $expense->withholding_amount > 0) {
            $lines[] = [$this->account('withholding', $expense, null, null, '4751'), 0, (float) $expense->withholding_amount];
        }
        $lines[] = [$creditorAccount, 0, (float) $expense->amount];

        return $lines;
    }

    private function customerPayment(SalesDocumentPayment $payment, ?int $userId): int
    {
        if (JournalEntry::whereMorphedTo('source', $payment)->where('event', 'payment')->exists()) {
            return 0;
        }
        $this->decisions = [];
        $this->taxAccounts = [];

        return DB::transaction(function () use ($payment, $userId): int {
            // Same order as receipt creation/deletion: document, receipt, fiscal year.
            $document = SalesDocument::lockForUpdate()->findOrFail($payment->sales_document_id);
            $payment = SalesDocumentPayment::lockForUpdate()->findOrFail($payment->id);
            $payment->setRelation('document', $document->loadMissing('customer'));
            $customerAccount = $document->collect_before_invoice && ! $document->locked_at ? $this->account('customer_advance', $payment, $document->customer, null, '438') : ($this->frozen($document, ['430'], 'receivable', asOf: $payment->paid_at->toDateString()) ?: $this->account('receivable', $document, $document->customer, $document->customer?->receivable_account_code, '430'));

            return $this->post($payment, 'payment', $payment->paid_at->toDateString(), __('Customer receipt :reference', ['reference' => $payment->reference ?: $payment->id]), $userId, [
                [$this->account($payment->method === 'cash' ? 'cash' : 'bank', $payment, null, null, $payment->method === 'cash' ? '570' : '572'), $payment->amount, 0], [$customerAccount, 0, $payment->amount],
            ]);
        });
    }

    private function supplierPayment(SupplierPayment $payment, ?int $userId): int
    {
        if (JournalEntry::whereMorphedTo('source', $payment)->where('event', 'payment')->exists()) {
            return 0;
        }
        $this->decisions = [];
        $this->taxAccounts = [];

        return DB::transaction(function () use ($payment, $userId): int {
            if ($payment->expense_id) {
                Expense::whereKey($payment->expense_id)->lockForUpdate()->firstOrFail();
            } elseif ($payment->purchase_document_id) {
                PurchaseDocument::whereKey($payment->purchase_document_id)->lockForUpdate()->firstOrFail();
            }
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);
            app(ReceivedInvoiceIdentity::class)->assertUsable($payment->expense_id ? Expense::findOrFail($payment->expense_id) : PurchaseDocument::findOrFail($payment->purchase_document_id));
            $payment->loadMissing(['expense.supplier', 'purchaseDocument.supplier', 'ledgerAccount']);
            $supplierAccount = $payment->expense?->creditor_account_code ?: ($payment->expense?->supplier?->payable_account_code ?? $payment->purchaseDocument?->supplier?->payable_account_code ?? ($payment->expense_id ? '410' : '400'));
            $fundingRole = match ($payment->method) {
                'cash' => 'cash','card' => 'credit_card','employee_funds' => 'employee_payable','other' => 'treasury_other',default => 'bank'
            };
            $fundingAccount = $this->account($fundingRole, $payment, null, $payment->ledgerAccount?->code, $payment->method === 'cash' ? '570' : '572');
            $supplier = $payment->expense?->supplier ?? $payment->purchaseDocument?->supplier;
            $role = str_starts_with($supplierAccount, '410') ? 'creditor' : 'supplier';
            $paymentDocument = $payment->expense ?? $payment->purchaseDocument;
            $supplierAccount = $this->frozen($paymentDocument, ['400', '410'], $role, asOf: $payment->paid_at->toDateString()) ?: $this->account($role, $payment, $supplier, $supplierAccount, $role === 'supplier' ? '400' : '410');

            return $this->post($payment, 'payment', $payment->paid_at->toDateString(), __('Supplier payment :reference', ['reference' => $payment->reference ?: $payment->id]), $userId, [
                [$supplierAccount, $payment->amount, 0], [$fundingAccount, 0, $payment->amount],
            ]);
        });
    }

    private function post(Model $source, string $event, string $date, string $description, ?int $userId, array $lines): int
    {
        if (JournalEntry::whereMorphedTo('source', $source)->where('event', $event)->exists()) {
            return 0;
        }
        $debit = round(collect($lines)->sum(fn ($line) => (float) $line[1]), 2);
        $credit = round(collect($lines)->sum(fn ($line) => (float) $line[2]), 2);
        if ($debit !== $credit) {
            throw ValidationException::withMessages(['accounting' => __('Unbalanced journal entry for :source.', ['source' => $description])]);
        }

        $this->periods->assertOpen($date);

        return DB::transaction(function () use ($source, $event, $date, $description, $userId, $lines) {
            FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
            if (JournalEntry::whereMorphedTo('source', $source)->where('event', $event)->exists()) {
                return 0;
            }
            $mapped = collect($lines)->filter(fn ($line) => (float) $line[1] !== 0.0 || (float) $line[2] !== 0.0)
                ->map(fn ($line) => ['account_code' => (string) $line[0], 'debit' => $line[1], 'credit' => $line[2]])->values()->all();
            $entry = app(JournalPoster::class)->post($date, $description, $mapped, $userId, 'automatic', $source, $event);
            AuditLog::create(['user_id' => $userId, 'event' => 'journal_accounts_resolved', 'auditable_type' => $entry->getMorphClass(), 'auditable_id' => $entry->id, 'new_values' => ['decisions' => $this->decisions, 'configuration_revision' => AccountingConfiguration::find(1)?->revision ?? 0]]);
            $this->decisions = [];

            return 1;
        });
    }

    private function taxAccount(?float $rate, string $direction, ?Model $operation = null): string
    {
        $key = $direction.'-'.($rate === null ? 'default' : round($rate, 2));

        $tax = TaxRate::when($rate !== null, fn ($q) => $q->where('rate', round($rate, 2)), fn ($q) => $q->where('is_default', true))->first();
        $field = $direction === 'output' ? 'output_account_code' : 'input_account_code';

        return $this->taxAccounts[$key] ??= $this->account($direction === 'output' ? 'output_vat' : 'input_vat', $operation, $tax, $tax?->$field, $direction === 'output' ? '477' : '472');
    }

    private function account(string $role, ?Model $operation, ?Model $specific, ?string $legacy, string $fallback, ?Model $category = null, ?string $categoryCode = null): string
    {
        if ($operation && in_array($operation->type ?? null, ['credit_note', 'supplier_credit_note'], true) && $operation->source_document_id && empty($operation->accounting_overrides[$role])) {
            $parent = $operation->newQuery()->find($operation->source_document_id);
            if ($parent) {
                $historical = app(PostedAccountSnapshot::class)->historicalChoice($parent, $role, $specific, $operation->issue_date->toDateString());
                if ($historical) {
                    $this->decisions[] = ['role' => $role, 'code' => $historical, 'origin' => 'posted_document', 'source_type' => $parent->getMorphClass(), 'source_id' => $parent->id];

                    return app(AccountingAccountPolicy::class)->valid($historical, $role)->code;
                }
            }
        }
        if (! $category && ($operation instanceof SalesDocument || $operation instanceof PurchaseDocument)) {
            $category = $operation->accountingCategory;
        }
        $decision = app(AccountingAccountPolicy::class)->decision($role, $operation, $specific, $category, legacySpecific: $legacy, categoryCode: $categoryCode, fallback: $fallback);
        $this->decisions[] = [...$decision, 'specific_type' => $specific?->getMorphClass(), 'specific_id' => $specific?->id, 'category_type' => $category?->getMorphClass(), 'category_id' => $category?->id];

        return $decision['code'];
    }

    private function frozen(Model $source, array $prefixes, string $role, ?string $event = null, ?string $asOf = null): ?string
    {
        $code = app(PostedAccountSnapshot::class)->control($source, $prefixes, $event, $asOf);
        if ($code) {
            $this->decisions[] = ['role' => $role, 'code' => $code, 'origin' => 'posted_document', 'source_type' => $source->getMorphClass(), 'source_id' => $source->id];
        }

        return $code;
    }
}
