<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use App\Models\JournalLine;

class CorporateTaxCalculator
{
    public function accountingResult(FiscalYear $year): float
    {
        $rows = JournalLine::query()->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')->where('journal_entries.fiscal_year_id', $year->id)->where('journal_entries.entry_kind', '!=', 'closing')->whereIn('ledger_accounts.type', ['income', 'expense'])->where('ledger_accounts.code', 'not like', '630%')->selectRaw('ledger_accounts.type, sum(journal_lines.debit) debit, sum(journal_lines.credit) credit')->groupBy('ledger_accounts.type')->get()->keyBy('type');
        $income = (float) (($rows['income']->credit ?? 0) - ($rows['income']->debit ?? 0));
        $expense = (float) (($rows['expense']->debit ?? 0) - ($rows['expense']->credit ?? 0));

        return round($income - $expense, 2);
    }

    public function calculate(FiscalYear $year, array $data): array
    {
        $result = $this->accountingResult($year);
        $base = max(0, $result + (float) $data['permanent_positive'] - (float) $data['permanent_negative'] + (float) $data['temporary_positive'] - (float) $data['temporary_negative'] - (float) $data['loss_carryforward']);
        [$gross, $bands] = $this->grossTax($year->year, $data['regime'], $base);
        $net = round($gross - (float) $data['deductions'] - (float) $data['withholdings'] - (float) $data['instalment_payments'], 2);

        return ['accounting_result' => $result, 'taxable_base' => round($base, 2), 'gross_tax' => $gross, 'net_tax' => $net, 'bands' => $bands, 'deadline' => $year->ends_on->copy()->addMonths(6)->addDays(25)->toDateString()];
    }

    private function grossTax(int $year, string $regime, float $base): array
    {
        if ($regime === 'new_entity') {
            return [round($base * .15, 2), [['base' => $base, 'rate' => 15]]];
        }
        if ($regime === 'micro') {
            if ($year >= 2026) {
                return $this->bands($base, 19, 21);
            }
            if ($year === 2025) {
                return $this->bands($base, 21, 22);
            }

            return [round($base * .23, 2), [['base' => $base, 'rate' => 23]]];
        }
        $rate = $regime === 'reduced' ? ($year >= 2026 ? 23 : ($year === 2025 ? 24 : 25)) : 25;

        return [round($base * $rate / 100, 2), [['base' => $base, 'rate' => $rate]]];
    }

    private function bands(float $base, float $firstRate, float $remainingRate): array
    {
        $first = min(50000, $base);
        $remaining = max(0, $base - $first);

        return [round($first * $firstRate / 100 + $remaining * $remainingRate / 100, 2), array_values(array_filter([['base' => $first, 'rate' => $firstRate], $remaining > 0 ? ['base' => $remaining, 'rate' => $remainingRate] : null]))];
    }
}
