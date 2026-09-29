<?php

namespace App\Services\Tax;

use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\TaxRate;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Read-only VAT movements, selected by hierarchy or explicit VAT mapping. */
class VatLedger
{
    public function make(CarbonInterface $from, CarbonInterface $to): array
    {
        // Keep inactive accounts/rates: deactivation must not erase historical VAT.
        $accounts = LedgerAccount::get(['id', 'code', 'parent_code', 'type'])->keyBy('code');
        $children = $accounts->groupBy('parent_code');
        $rates = TaxRate::where('kind', 'vat')->get(['input_account_code', 'output_account_code']);
        $result = [];
        foreach (['input' => ['472', 'asset'], 'output' => ['477', 'liability']] as $direction => [$root, $nature]) {
            $pending = [$root, ...$rates->pluck($direction.'_account_code')->filter()->all()];
            $selected = [];
            while ($pending) {
                $code = array_pop($pending);
                if (isset($selected[$code])) {
                    continue;
                }
                $account = $accounts->get($code);
                if (! $account || $account->type !== $nature) {
                    throw ValidationException::withMessages(['accounting' => __('Review the VAT ledger account configuration.')]);
                }
                $selected[$code] = $account->id;
                foreach ($children->get($code, collect()) as $child) {
                    $pending[] = $child->code;
                }
            }
            $codes = array_flip($selected);
            $rows = JournalLine::whereIn('ledger_account_id', array_values($selected))
                ->whereHas('entry', fn ($query) => $query->where('status', 'posted')->whereBetween('entry_date', [$from, $to])
                    ->whereNotIn('entry_kind', ['closing', 'opening', 'closing_reversal', 'opening_reversal', 'tax_settlement']))
                ->selectRaw('ledger_account_id, sum(debit) as debit, sum(credit) as credit')
                ->groupBy('ledger_account_id')->get()->map(function ($line) use ($codes, $direction) {
                    $debit = round((float) $line->debit, 2);
                    $credit = round((float) $line->credit, 2);

                    return ['account_code' => (string) $codes[$line->ledger_account_id], 'debit' => $debit, 'credit' => $credit,
                        'balance' => round($direction === 'input' ? $debit - $credit : $credit - $debit, 2)];
                })->sortBy('account_code')->values();
            $result[$direction] = ['accounts' => $rows->all(), 'total' => round((float) $rows->sum('balance'), 2)];
        }

        return $result;
    }
}
