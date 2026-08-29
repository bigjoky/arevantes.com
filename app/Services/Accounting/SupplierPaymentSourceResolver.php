<?php

namespace App\Services\Accounting;

use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\LedgerAccount;
use App\Models\Person;
use Illuminate\Validation\ValidationException;

class SupplierPaymentSourceResolver
{
    public function __construct(private EmployeeLedgerAccountManager $employees) {}

    /** @return array<string, mixed> */
    public function options(): array
    {
        return [
            'bankAccounts' => BankAccount::with('ledgerAccount:id,code')->where('is_active', true)->whereNotNull('ledger_account_id')->orderBy('name')->get()->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'label' => $account->name.' · '.($account->iban ? '•••• '.substr(preg_replace('/\s+/', '', $account->iban), -4) : $account->ledgerAccount?->code),
            ])->values(),
            'cards' => BankFinancialProduct::with('ledgerAccount:id,code')->where('type', 'credit_card')->where('is_active', true)->whereNotNull('ledger_account_id')->orderBy('name')->get()->map(fn (BankFinancialProduct $card) => [
                'id' => $card->id,
                'label' => $card->name.($card->identifier_last_four ? ' · •••• '.$card->identifier_last_four : '').' · '.$card->ledgerAccount?->code,
            ])->values(),
            'employees' => Person::where('category', 'staff')->orderBy('first_name')->orderBy('last_name')->get()->map(fn (Person $employee) => [
                'id' => $employee->id,
                'label' => ($employee->company_name ?: trim($employee->first_name.' '.($employee->last_name ?? ''))).' · '.($employee->employee_payable_account_code ?: __('account pending')),
            ])->values(),
            'cashAccounts' => $this->ledgerOptions(['570']),
            'otherAccounts' => $this->ledgerOptions(['57']),
        ];
    }

    /** @return array{funding_source_type: string, funding_source_id: int, ledger_account_id: int} */
    public function resolve(string $method, string $type, int $id): array
    {
        $expected = match ($method) {
            'bank_transfer', 'direct_debit' => 'bank_account',
            'card' => 'card',
            'cash', 'other' => 'ledger_account',
            'employee_funds' => 'employee',
            default => null,
        };
        if ($expected === null || $type !== $expected) {
            throw ValidationException::withMessages(['funding_source_id' => __('Select a payment source compatible with the payment method.')]);
        }

        $ledger = match ($type) {
            'bank_account' => BankAccount::where('is_active', true)->find($id)?->ledgerAccount,
            'card' => BankFinancialProduct::where('type', 'credit_card')->where('is_active', true)->find($id)?->ledgerAccount,
            'employee' => $this->employeeAccount($id),
            'ledger_account' => $this->ledgerAccount($id, $method),
        };
        if (! $ledger || ! $ledger->is_active || ! $ledger->is_postable) {
            throw ValidationException::withMessages(['funding_source_id' => __('The selected payment source has no active postable accounting account.')]);
        }

        return ['funding_source_type' => $type, 'funding_source_id' => $id, 'ledger_account_id' => $ledger->id];
    }

    private function employeeAccount(int $id): ?LedgerAccount
    {
        $employee = Person::where('category', 'staff')->find($id);
        if (! $employee) {
            return null;
        }
        $this->employees->sync($employee);

        return LedgerAccount::where('code', $employee->fresh()->employee_payable_account_code)->first();
    }

    private function ledgerAccount(int $id, string $method): ?LedgerAccount
    {
        return LedgerAccount::where('is_active', true)->where('is_postable', true)
            ->when($method === 'cash', fn ($query) => $query->where('code', 'like', '570%'), fn ($query) => $query->where('code', 'like', '57%'))
            ->find($id);
    }

    /** @param list<string> $prefixes */
    private function ledgerOptions(array $prefixes): mixed
    {
        return LedgerAccount::where('is_active', true)->where('is_postable', true)
            ->where(fn ($query) => collect($prefixes)->each(fn ($prefix) => $query->orWhere('code', 'like', $prefix.'%')))
            ->orderBy('code')->get(['id', 'code', 'name'])->map(fn (LedgerAccount $account) => ['id' => $account->id, 'label' => $account->code.' · '.$account->name])->values();
    }
}
