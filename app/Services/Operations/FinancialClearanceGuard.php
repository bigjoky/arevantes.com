<?php

namespace App\Services\Operations;

use App\Models\Installation;
use Illuminate\Validation\ValidationException;

class FinancialClearanceGuard
{
    public function assertAllows(Installation $installation, string $operation): void
    {
        if (! in_array($operation, ['installation', 'commissioning', 'reserve', 'procure'], true)) {
            return;
        }

        if (in_array($installation->financial_status, ['pending', 'partial'], true)) {
            throw ValidationException::withMessages([
                'financial_status' => __('The acceptance payment must be confirmed before installation, commissioning, stock reservation or procurement.'),
            ]);
        }
    }
}
