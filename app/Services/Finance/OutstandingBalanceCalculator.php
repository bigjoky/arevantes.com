<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class OutstandingBalanceCalculator
{
    public function receivable(SalesDocument $document): float
    {
        return (float) $this->receivableDecimal($document);
    }

    public function purchase(PurchaseDocument $document): float
    {
        $credits = array_key_exists('credited_total', $document->getAttributes())
            ? abs((float) $document->credited_total)
            : abs((float) $document->creditNotes()->sum('amount'));

        return (float) $this->net($document->amount, $document->paid_amount, (string) $credits);
    }

    public function expense(Expense $expense): float
    {
        return (float) $this->net($expense->amount, $expense->paid_amount);
    }

    public function receivableDecimal(SalesDocument $document): string
    {
        $credits = array_key_exists('credited_total', $document->getAttributes())
            ? $document->credited_total
            : $document->childDocuments()->where('type', 'credit_note')->whereNotIn('status', ['draft', 'cancelled'])->sum('amount');

        return $this->net($document->amount, $document->paid_amount, (string) BigDecimal::of((string) ($credits ?? 0))->abs());
    }

    public function net(mixed $amount, mixed $paid, mixed $credits = '0'): string
    {
        return (string) BigDecimal::max('0', BigDecimal::of((string) $amount)->minus((string) $paid)->minus((string) $credits))->toScale(2, RoundingMode::HalfUp);
    }
}
