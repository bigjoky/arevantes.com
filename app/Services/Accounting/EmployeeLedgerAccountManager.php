<?php

namespace App\Services\Accounting;

use App\Models\Person;
use Illuminate\Validation\ValidationException;

class EmployeeLedgerAccountManager
{
    public function sync(Person $employee): void
    {
        if ($employee->category !== 'staff') {
            return;
        }
        foreach (['employee_advance_account_code' => 'employee_advance', 'employee_payable_account_code' => 'employee_payable'] as $field => $role) {
            if (! $employee->$field) {
                throw ValidationException::withMessages(['accounting' => __('Assign the employee accounting accounts explicitly before registering this operation.')]);
            }
            app(AccountingAccountPolicy::class)->valid($employee->$field, $role);
        }
    }

    public function syncAll(): void
    {
        Person::where('category', 'staff')->orderBy('id')->each(fn ($p) => $this->sync($p));
    }
}
