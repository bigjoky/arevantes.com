<?php

namespace App\Services\Tax;

use App\Models\TaxReturn;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VatSettlement
{
    public function __construct(private VatReport $report, private AccountingSynchronizer $synchronizer, private JournalPoster $poster) {}

    public function prepare(int $year, int $quarter, int $userId): TaxReturn
    {
        if (TaxReturn::where(['tax_type' => 'vat', 'year' => $year, 'quarter' => $quarter])->exists()) {
            throw ValidationException::withMessages(['quarter' => __('This VAT period has already been prepared.')]);
        }
        $this->synchronizer->sync($userId);
        $data = $this->report->make($year, $quarter);
        if (! $data['accountingCheck']['matches']) {
            throw ValidationException::withMessages(['quarter' => __('Resolve VAT differences between documents and accounting before preparing the return.')]);
        }
        $output = (float) $data['summary']['outputVat'];
        $input = (float) $data['summary']['deductibleVat'];
        $result = round($output - $input, 2);
        if ($output === 0.0 && $input === 0.0) {
            throw ValidationException::withMessages(['quarter' => __('There are no VAT movements in this period.')]);
        }

        return DB::transaction(function () use ($year, $quarter, $userId, $data, $output, $input, $result) {
            $option = $result > 0 ? 'payable' : ($result < 0 ? 'compensate' : 'none');
            $settlementStatus = $result > 0 ? 'pending' : ($result < 0 ? 'awaiting_election' : 'settled');
            $return = TaxReturn::create(['tax_type' => 'vat', 'year' => $year, 'quarter' => $quarter, 'period_from' => $data['period']['from'], 'period_to' => $data['period']['to'], 'status' => 'prepared', 'settlement_status' => $settlementStatus, 'output_vat' => $output, 'input_vat' => $input, 'result' => $result, 'result_option' => $option, 'snapshot' => ['summary' => $data['summary'], 'rates' => $data['rates'], 'issued_count' => count($data['issued']), 'received_count' => count($data['received'])], 'prepared_by' => $userId, 'prepared_at' => now()]);
            $lines = [];
            foreach (['output', 'input'] as $direction) {
                foreach ($data['accountingCheck']['accounts'][$direction]['accounts'] as $account) {
                    $balance = (float) $account['balance'];
                    if ($balance === 0.0) {
                        continue;
                    }
                    $debit = $direction === 'output' ? $balance : -$balance;
                    $lines[] = ['account_code' => $account['account_code'], 'debit' => max($debit, 0), 'credit' => max(-$debit, 0)];
                }
            }
            if ($result > 0) {
                $lines[] = ['account_code' => '4750', 'debit' => 0, 'credit' => $result];
            } elseif ($result < 0) {
                $lines[] = ['account_code' => '4700', 'debit' => abs($result), 'credit' => 0];
            }
            $entry = $this->poster->post($data['period']['to'], __('VAT settlement :year Q:quarter', compact('year', 'quarter')), $lines, $userId, 'tax_settlement', $return, 'vat_settlement');
            $return->update(['journal_entry_id' => $entry->id, 'snapshot' => [...$return->snapshot, 'vat_accounts' => $data['accountingCheck']['accounts']]]);

            return $return;
        });
    }
}
