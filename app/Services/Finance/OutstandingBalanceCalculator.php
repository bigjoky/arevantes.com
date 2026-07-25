<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;

class OutstandingBalanceCalculator
{
    public function receivable(SalesDocument $document): float
    {
        return max(0, round((float) $document->amount - (float) $document->paid_amount, 2));
    }

    public function purchase(PurchaseDocument $document): float
    {
        $credits = array_key_exists('credited_total', $document->getAttributes())
            ? abs((float) $document->credited_total)
            : abs((float) $document->creditNotes()->sum('amount'));

        return max(0, round((float) $document->amount - $credits - (float) $document->paid_amount, 2));
    }

    public function expense(Expense $expense): float
    {
        return max(0, round((float) $expense->amount - (float) $expense->paid_amount, 2));
    }
}
