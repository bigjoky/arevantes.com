<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeLedgerAccountManager
{
    public function sync(Person $employee): void
    {
        if ($employee->category !== 'staff' || ! Schema::hasColumn('people', 'employee_payable_account_code')) {
            return;
        }

        $identifier = $employee->tax_id ?: $employee->document_number ?: $employee->employmentProfile?->employee_number ?: (string) $employee->id;
        $digits = preg_replace('/\D+/', '', (string) $identifier) ?: (string) $employee->id;
        $suffix = str_pad(substr($digits, -3), 3, '0', STR_PAD_LEFT);
        $name = $employee->company_name ?: trim($employee->first_name.' '.($employee->last_name ?? ''));

        DB::transaction(function () use ($employee, $suffix, $name): void {
            $advance = $this->account('460', $suffix, $employee->employee_advance_account_code, __('Advances — :name', ['name' => $name]), 'asset');
            $payable = $this->account('465', $suffix, $employee->employee_payable_account_code, __('Employee expenses payable — :name', ['name' => $name]), 'liability');

            DB::table('people')->where('id', $employee->id)->update([
                'employee_advance_account_code' => $advance->code,
                'employee_payable_account_code' => $payable->code,
                'updated_at' => now(),
            ]);
            $employee->setAttribute('employee_advance_account_code', $advance->code);
            $employee->setAttribute('employee_payable_account_code', $payable->code);
        });
    }

    public function syncAll(): void
    {
        Person::where('category', 'staff')->with('employmentProfile')->orderBy('id')->each(fn (Person $employee) => $this->sync($employee));
    }

    private function account(string $prefix, string $suffix, ?string $currentCode, string $name, string $type): LedgerAccount
    {
        if ($currentCode && str_ends_with($currentCode, $suffix)) {
            $existing = LedgerAccount::where('code', $currentCode)->first();
            if ($existing) {
                $existing->update(['name' => $name, 'is_active' => true]);

                return $existing;
            }
        }

        for ($sequence = 0; $sequence <= 999999; $sequence++) {
            $code = $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT).$suffix;
            if (! LedgerAccount::where('code', $code)->exists()) {
                return LedgerAccount::create([
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'parent_code' => $prefix,
                    'level' => strlen($code),
                    'is_postable' => true,
                    'is_active' => true,
                    'source' => 'custom',
                ]);
            }
        }

        throw new \RuntimeException("No accounting subaccount is available for employee suffix {$suffix}.");
    }
}
