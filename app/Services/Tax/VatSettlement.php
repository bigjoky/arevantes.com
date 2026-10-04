<?php

namespace App\Services\Tax;

use App\Models\FiscalYear;
use App\Models\TaxReturn;
use App\Services\Accounting\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VatSettlement
{
    public function __construct(private VatReport $report, private JournalPoster $poster) {}

    public function preview(array $data): array
    {
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
        $result = (float) $data['summary']['result'];
        if ($result > 0) {
            $lines[] = ['account_code' => '4750', 'debit' => 0, 'credit' => $result];
        } elseif ($result < 0) {
            $lines[] = ['account_code' => '4700', 'debit' => abs($result), 'credit' => 0];
        }
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);

        return ['lines' => $lines, 'debit' => $debit, 'credit' => $credit,
            'ready' => $data['accountingCheck']['matches'] && $debit > 0 && abs($debit - $credit) < 0.001];
    }

    public function prepare(int $year, int $quarter, int $userId): TaxReturn
    {
        if (TaxReturn::where(['tax_type' => 'vat', 'year' => $year, 'quarter' => $quarter])->exists()) {
            throw ValidationException::withMessages(['quarter' => __('This VAT period has already been prepared.')]);
        }
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
            // Serialize creation with other settlements and accounting posting in the year.
            FiscalYear::where('year', $year)->lockForUpdate()->firstOrFail();
            if (TaxReturn::where(['tax_type' => 'vat', 'year' => $year, 'quarter' => $quarter])->exists()) {
                throw ValidationException::withMessages(['quarter' => __('This VAT period has already been prepared.')]);
            }
            $option = $result > 0 ? 'payable' : ($result < 0 ? 'compensate' : 'none');
            $return = TaxReturn::create(['tax_type' => 'vat', 'year' => $year, 'quarter' => $quarter, 'period_from' => $data['period']['from'], 'period_to' => $data['period']['to'], 'status' => 'draft', 'settlement_status' => 'unposted', 'output_vat' => $output, 'input_vat' => $input, 'result' => $result, 'result_option' => $option, 'snapshot' => $this->evidence($data), 'prepared_by' => $userId, 'prepared_at' => now()]);

            return $return;
        });
    }

    private function evidence(array $data): array
    {
        // Convert collections to stable, persisted JSON and preserve document identities.
        $data = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        foreach (['issued', 'received'] as $book) {
            usort($data[$book], fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        }

        return ['version' => 'vat-settlement-v1', 'sources' => $data,
            'fingerprint' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
            'summary' => $data['summary'], 'rates' => $data['rates'],
            'vat_accounts' => $data['accountingCheck']['accounts'], 'entry' => $this->preview($data)];
    }

    public function review(TaxReturn $return, int $userId, string $reason, bool $refresh = false): TaxReturn
    {
        return DB::transaction(function () use ($return, $userId, $reason, $refresh) {
            $return = TaxReturn::lockForUpdate()->findOrFail($return->id);
            abort_unless($return->tax_type === 'vat' && in_array($return->status, ['draft', 'approved'], true) && ! $return->journal_entry_id, 422);
            abort_unless($refresh || $return->status === 'draft', 422);
            $data = $this->report->make($return->year, $return->quarter);
            $evidence = $this->evidence($data);
            if (! $evidence['entry']['ready']) {
                throw ValidationException::withMessages(['review' => __('Resolve VAT differences between documents and accounting before preparing the return.')]);
            }
            if (! $refresh && ($return->snapshot['fingerprint'] ?? null) !== $evidence['fingerprint']) {
                throw ValidationException::withMessages(['review' => __('The VAT books have changed. Refresh and review the draft again.')]);
            }
            $history = $return->snapshot['review_history'] ?? [];
            $history[] = ['action' => $refresh ? 'refreshed' : 'approved', 'user_id' => $userId, 'reason' => $reason, 'at' => now()->toISOString(), 'fingerprint' => $evidence['fingerprint']];
            $return->update(['status' => $refresh ? 'draft' : 'approved',
                'output_vat' => $data['summary']['outputVat'], 'input_vat' => $data['summary']['deductibleVat'],
                'result_option' => $data['summary']['result'] > 0 ? 'payable' : ($data['summary']['result'] < 0 ? 'compensate' : 'none'),
                'result' => $data['summary']['result'], 'snapshot' => [...$evidence, 'review_history' => $history]]);

            return $return;
        });
    }

    public function post(TaxReturn $return, int $userId): TaxReturn
    {
        return DB::transaction(function () use ($return, $userId) {
            $return = TaxReturn::lockForUpdate()->findOrFail($return->id);
            abort_unless($return->tax_type === 'vat' && $return->status === 'approved' && ! $return->journal_entry_id, 422);
            // JournalPoster uses the same year lock; keep the check and posting together.
            FiscalYear::where('year', $return->year)->lockForUpdate()->firstOrFail();
            $evidence = $this->evidence($this->report->make($return->year, $return->quarter));
            if (! $evidence['entry']['ready'] || ($return->snapshot['fingerprint'] ?? null) !== $evidence['fingerprint']) {
                throw ValidationException::withMessages(['review' => __('The VAT books have changed. Refresh and review the draft again.')]);
            }
            $year = $return->year;
            $quarter = $return->quarter;
            $entry = $this->poster->post($return->period_to->toDateString(), __('VAT settlement :year Q:quarter', compact('year', 'quarter')), $evidence['entry']['lines'], $userId, 'tax_settlement', $return, 'vat_settlement');
            $result = (float) $return->result;
            $return->update(['status' => 'prepared', 'journal_entry_id' => $entry->id,
                'result_option' => $result > 0 ? 'payable' : ($result < 0 ? 'compensate' : 'none'),
                'settlement_status' => $result > 0 ? 'pending' : ($result < 0 ? 'awaiting_election' : 'settled'),
                'snapshot' => [...$return->snapshot, 'posted_by' => $userId, 'posted_at' => now()->toISOString()]]);

            return $return;
        });
    }
}
