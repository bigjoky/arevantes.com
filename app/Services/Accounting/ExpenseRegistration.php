<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Services\Documents\DocumentNumber;
use App\Services\Finance\ReceivedInvoiceIdentity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Registers an already validated expense using the ordinary accounting workflow. */
final class ExpenseRegistration
{
    public function __construct(private AccountingSynchronizer $accounting, private DocumentNumber $numbers) {}

    /** @param array<int, array<string, mixed>> $taxLines
     * @param  array<int, array<string, mixed>>  $storedFiles
     */
    public function register(array $data, array $taxLines, array $storedFiles, int $userId, ?string $accountingDate = null): Expense
    {
        return DB::transaction(function () use ($data, $taxLines, $storedFiles, $userId, $accountingDate) {
            if (in_array($data['type'], ['invoice', 'simplified_invoice'], true)) {
                app(ReceivedInvoiceIdentity::class)->lockInput($data['issuer_country'] ?? 'ES', $data['issuer_tax_id'] ?? null, 'invoice', $data['document_number'] ?? '');
            }
            $data = app(ExpenseCreditorRegistry::class)->newInvoice($data, true);
            $accountingDecision = Arr::pull($data, '_accounting_control_decision');
            $number = $this->numbers->next('expense', $data['expense_date'])['number'];
            $amount = $data['tax_calculation_snapshot']['result']['totals']['total'] ?? round((float) $data['subtotal'] + (float) $data['tax_amount'] - (float) $data['withholding_amount'], 2);
            $expense = Expense::create([...$data, 'created_by' => $userId, 'number' => $number, 'amount' => $amount, 'status' => 'pending']);
            $expense->taxLines()->createMany($taxLines);
            foreach ($storedFiles as $storedFile) {
                $expense->attachments()->create([...$storedFile, 'uploaded_by' => $userId]);
            }
            $this->accounting->syncExpense($expense, $userId, $accountingDecision, $accountingDate);

            return $expense;
        });
    }
}
