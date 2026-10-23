<?php

namespace App\Services\Tax;

use App\Models\CorporateTaxReturn;
use App\Models\TaxReturn;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Read projection of existing documents. These amounts are not declaration boxes. */
class FiscalWorkspace
{
    public const VERSION = 'documentary-fiscal-summary-v1';

    public static function decimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP);
    }

    public function documents(string $from, string $to): Builder
    {
        $sales = DB::table('sales_documents')->whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereNotIn('status', ['draft', 'cancelled'])->whereBetween('issue_date', [$from, $to])
            ->selectRaw("id, 'sales' as source, issue_date as date, number, 'issued' as direction, case when type='credit_note' then -abs(subtotal) else subtotal end as base, case when type='credit_note' then -abs(tax_amount) else tax_amount end as vat, 0 as withholding");
        $purchases = DB::table('purchase_documents')->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNotIn('status', ['draft', 'review_required', 'cancelled'])->whereNull('cancelled_at')->whereBetween('issue_date', [$from, $to])
            ->selectRaw("id, 'purchases' as source, issue_date as date, number, 'received' as direction, case when type='supplier_credit_note' then -abs(subtotal) else subtotal end as base, case when type='supplier_credit_note' then -abs(tax_amount) else tax_amount end as vat, 0 as withholding");
        $expenses = DB::table('expenses')->where('type', 'invoice')->where('currency_code', 'EUR')->whereNotIn('status', ['draft', 'cancelled'])->whereBetween('expense_date', [$from, $to])
            ->selectRaw("id, 'expenses' as source, expense_date as date, number, 'received' as direction, subtotal as base, tax_amount as vat, withholding_amount as withholding");

        return DB::query()->fromSub($sales->unionAll($purchases)->unionAll($expenses), 'fiscal_documents');
    }

    public function totals(Builder $documents): array
    {
        $r = (clone $documents)->selectRaw("count(*) as documents, coalesce(sum(case when direction='issued' then vat else 0 end),0) as output_vat, coalesce(sum(case when direction='received' then vat else 0 end),0) as supported_vat, coalesce(sum(withholding),0) as withholding")->first();

        return ['documents' => (int) $r->documents, 'outputVat' => self::decimal($r->output_vat), 'supportedVat' => self::decimal($r->supported_vat), 'withholding' => self::decimal($r->withholding), 'deductibleVat' => null, 'result' => null, 'nextDeadline' => null];
    }

    public function series(Builder $documents): array
    {
        return (clone $documents)->selectRaw("substr(cast(date as text),1,7) as month, sum(case when direction='issued' then vat else 0 end) as output_vat, sum(case when direction='received' then vat else 0 end) as supported_vat")
            ->groupByRaw('substr(cast(date as text),1,7)')->orderBy('month')->get()->map(fn ($r) => ['month' => $r->month, 'outputVat' => self::decimal($r->output_vat), 'supportedVat' => self::decimal($r->supported_vat)])->all();
    }

    public function declarations(int $year): Builder
    {
        $vat = DB::table('tax_returns')->where('tax_type', 'vat')->where('year', $year)->selectRaw("id, '303' as model, year, quarter, status, period_from, period_to, result as amount, prepared_at, filed_on, filing_reference");
        $corporate = DB::table('corporate_tax_returns as c')->join('fiscal_years as y', 'y.id', '=', 'c.fiscal_year_id')->where('y.year', $year)->selectRaw("c.id, '200' as model, y.year, null as quarter, c.status, y.starts_on as period_from, y.ends_on as period_to, c.net_tax as amount, c.prepared_at, c.filed_on, c.filing_reference");

        return DB::query()->fromSub($vat->unionAll($corporate), 'declarations');
    }

    public function declarationRows($rows): array
    {
        // One bounded query per source, never one query per declaration.
        $vat = TaxReturn::whereIn('id', collect($rows)->where('model', '303')->pluck('id'))->get()->keyBy('id');
        $corporate = CorporateTaxReturn::whereIn('id', collect($rows)->where('model', '200')->pluck('id'))->get()->keyBy('id');

        return collect($rows)->map(function ($r) use ($vat, $corporate) {
            $record = $r->model === '303' ? $vat->get($r->id) : $corporate->get($r->id);
            $evidence = $this->hasEvidence($r, $record);
            $href = $r->model === '303' ? '/tax?year='.$r->year.'&quarter='.$r->quarter : '/corporate-tax?year='.$r->year;

            return ['id' => $r->id, 'model' => $r->model, 'period' => $r->quarter ? $r->quarter.'T '.$r->year : (string) $r->year, 'from' => $r->period_from, 'to' => $r->period_to, 'status' => $r->status === 'filed' && ! $evidence ? 'evidence_pending' : $r->status, 'amount' => self::decimal($r->amount), 'preparedAt' => $r->prepared_at, 'filedOn' => $r->filed_on, 'reference' => $r->filing_reference, 'evidence' => (bool) $evidence, 'receiptHref' => $evidence ? ($r->model === '303' ? '/tax/returns/'.$r->id.'/receipt' : '/corporate-tax/returns/'.$r->id.'/receipt') : null, 'href' => '/fiscal/declaraciones/'.$r->model.'/'.$r->id, 'manageHref' => $href, 'deadline' => null];
        })->all();
    }

    private function hasEvidence(object $row, mixed $record): bool
    {
        if ($row->status !== 'filed' || ! $row->filed_on || ! $row->filing_reference || ! $record?->receipt_path || ! preg_match('/^[a-f0-9]{64}$/i', $record->receipt_sha256 ?? '')) {
            return false;
        }
        if (substr($row->filed_on, 0, 10) < substr($row->period_to, 0, 10)) {
            return false;
        }
        try {
            $disk = Storage::disk(config('filesystems.private'));
            if (! $disk->exists($record->receipt_path)) {
                return false;
            }
            $stream = $disk->readStream($record->receipt_path);
            if (! is_resource($stream)) {
                return false;
            }
            try {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);

                return hash_equals(strtolower($record->receipt_sha256), hash_final($hash));
            } finally {
                fclose($stream);
            }
        } catch (\Throwable) {
            // Missing or inaccessible evidence never becomes a confirmed presentation.
            return false;
        }
    }
}
