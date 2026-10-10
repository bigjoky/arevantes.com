<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use App\Services\Documents\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingSynchronizer
{
    public function __construct(private AccountingPeriodManager $periods, private DocumentNumber $numbers) {}

    private array $accounts = [];

    private array $taxAccounts = [];

    public function sync(?int $userId = null): int
    {
        $created = 0;
        SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNot('status', 'draft')->each(fn ($document) => $created += $this->salesDocument($document, $userId));
        PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])
            ->whereNotIn('status', ['draft', 'review_required'])
            ->where(fn ($query) => $query->whereNull('matching_status')->orWhereNot('matching_status', 'review_required'))
            ->each(fn ($document) => $created += $this->purchaseDocument($document, $userId));
        Expense::each(fn ($expense) => $created += $this->expense($expense, $userId));
        SalesDocumentPayment::each(fn ($payment) => $created += $this->customerPayment($payment, $userId));
        SupplierPayment::each(fn ($payment) => $created += $this->supplierPayment($payment, $userId));

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

        return $this->purchaseDocument($document, $userId);
    }

    public function syncExpense(Expense $expense, ?int $userId = null): int
    {
        return $this->expense($expense, $userId);
    }

    public function syncCustomerPayment(SalesDocumentPayment $payment, ?int $userId = null): int
    {
        return $this->customerPayment($payment, $userId);
    }

    public function syncSupplierPayment(SupplierPayment $payment, ?int $userId = null): int
    {
        return $this->supplierPayment($payment, $userId);
    }

    private function salesDocument(SalesDocument $document, ?int $userId): int
    {
        return $this->post($document, 'document', $document->issue_date->toDateString(), $document->number, $userId, $this->salesDocumentLines($document));
    }

    public function salesDocumentLines(SalesDocument $document): array
    {
        $creditNote = $document->type === 'credit_note';
        $document->loadMissing(['lines.product', 'customer']);
        $customerAccount = $document->customer?->receivable_account_code ?: '430';
        $revenue = $document->lines->groupBy(fn ($line) => $line->product?->sale_account_code ?: '700')->map(fn ($lines) => round((float) $lines->sum(fn ($line) => (float) $line->subtotal - (float) $line->discount_amount), 2));
        if ($revenue->isEmpty()) {
            $revenue = collect(['700' => abs((float) $document->subtotal - (float) $document->discount_amount)]);
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
            $lines[] = [$this->taxAccount($rate === 'default' ? null : (float) $rate, 'output'), $creditNote ? $amount : 0, $creditNote ? 0 : $amount];
        }

        return $lines;
    }

    private function purchaseDocument(PurchaseDocument $document, ?int $userId): int
    {
        $creditNote = $document->type === 'supplier_credit_note';
        $document->loadMissing(['lines.product', 'supplier']);
        $supplierAccount = $document->supplier?->payable_account_code ?: '400';
        $costs = $document->lines->groupBy(fn ($line) => $line->product?->purchase_account_code ?: '600')->map(fn ($lines) => round((float) $lines->sum('subtotal'), 2));
        if ($costs->isEmpty()) {
            $costs = collect(['600' => (float) $document->subtotal - (float) $document->shipping_cost]);
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
            $lines[] = [$this->taxAccount($rate === 'default' ? null : (float) $rate, 'input'), $creditNote ? 0 : abs($amount), $creditNote ? abs($amount) : 0];
        }
        $lines[] = [$supplierAccount, $creditNote ? abs((float) $document->amount) : 0, $creditNote ? 0 : abs((float) $document->amount)];

        return $this->post($document, 'document', $document->issue_date->toDateString(), $document->number, $userId, $lines);
    }

    private function expense(Expense $expense, ?int $userId): int
    {
        return $this->post($expense, 'document', $expense->expense_date->toDateString(), $expense->number, $userId, $this->expenseLines($expense));
    }

    public function expenseLines(Expense $expense): array
    {
        $expense->loadMissing(['category', 'supplier', 'taxLines']);
        $creditorAccount = $expense->supplier?->payable_account_code ?: '410';
        $deductibleTax = round((float) $expense->taxLines->sum('deductible_amount'), 2);
        if ($expense->taxLines->isEmpty()) {
            $deductibleTax = (float) $expense->tax_amount;
        }
        $nonDeductibleTax = round((float) $expense->tax_amount - $deductibleTax, 2);
        $lines = [
            [$expense->category?->expense_account_code ?: '629', round((float) $expense->subtotal + $nonDeductibleTax, 2), 0],
        ];
        foreach ($expense->taxLines->where('deductible_amount', '>', 0)->groupBy(fn ($line) => (string) $line->tax_rate) as $rate => $taxLines) {
            $lines[] = [$this->taxAccount((float) $rate, 'input'), round((float) $taxLines->sum('deductible_amount'), 2), 0];
        }
        if ($expense->taxLines->isEmpty() && $deductibleTax > 0) {
            $lines[] = [$this->taxAccount((float) $expense->subtotal > 0 ? (float) $expense->tax_amount / (float) $expense->subtotal * 100 : null, 'input'), $deductibleTax, 0];
        }
        if ((float) $expense->withholding_amount > 0) {
            $lines[] = ['4751', 0, (float) $expense->withholding_amount];
        }
        $lines[] = [$creditorAccount, 0, (float) $expense->amount];

        return $lines;
    }

    private function customerPayment(SalesDocumentPayment $payment, ?int $userId): int
    {
        $payment->loadMissing('document.customer');
        $customerAccount = $payment->document?->customer?->receivable_account_code ?: '430';

        return $this->post($payment, 'payment', $payment->paid_at->toDateString(), __('Customer receipt :reference', ['reference' => $payment->reference ?: $payment->id]), $userId, [
            [$payment->method === 'cash' ? '570' : '572', $payment->amount, 0], [$customerAccount, 0, $payment->amount],
        ]);
    }

    private function supplierPayment(SupplierPayment $payment, ?int $userId): int
    {
        $payment->loadMissing(['expense.supplier', 'purchaseDocument.supplier', 'ledgerAccount']);
        $supplierAccount = $payment->expense?->supplier?->payable_account_code ?? $payment->purchaseDocument?->supplier?->payable_account_code ?? ($payment->expense_id ? '410' : '400');
        $fundingAccount = $payment->ledgerAccount?->code ?: ($payment->method === 'cash' ? '570' : '572');

        return $this->post($payment, 'payment', $payment->paid_at->toDateString(), __('Supplier payment :reference', ['reference' => $payment->reference ?: $payment->id]), $userId, [
            [$supplierAccount, $payment->amount, 0], [$fundingAccount, 0, $payment->amount],
        ]);
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

        DB::transaction(function () use ($source, $event, $date, $description, $userId, $lines) {
            $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->first();
            if ($year?->status === 'closed') {
                throw ValidationException::withMessages(['accounting' => __('The fiscal year for :date is closed.', ['date' => $date])]);
            }
            $entry = JournalEntry::create(['fiscal_year_id' => $year?->id, 'created_by' => $userId, 'entry_date' => $date, 'number' => $this->number($date), 'description' => $description, 'source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'event' => $event, 'status' => 'posted', 'posted_at' => now()]);
            foreach ($lines as [$code, $debit, $credit]) {
                if ((float) $debit === 0.0 && (float) $credit === 0.0) {
                    continue;
                }
                $entry->lines()->create(['ledger_account_id' => $this->account($code), 'debit' => $debit, 'credit' => $credit]);
            }
        });

        return 1;
    }

    private function account(string $code): int
    {
        return $this->accounts[$code] ??= LedgerAccount::where('code', $code)->valueOrFail('id');
    }

    private function number(string $date): string
    {
        return $this->numbers->next('journal_entry', $date, 6)['number'];
    }

    private function taxAccount(?float $rate, string $direction): string
    {
        $key = $direction.'-'.($rate === null ? 'default' : round($rate, 2));

        return $this->taxAccounts[$key] ??= (TaxRate::when($rate !== null, fn ($query) => $query->where('rate', round($rate, 2)), fn ($query) => $query->where('is_default', true))->value($direction === 'output' ? 'output_account_code' : 'input_account_code') ?: ($direction === 'output' ? '477' : '472'));
    }
}
