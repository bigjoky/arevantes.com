<?php

namespace App\Services\Billing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read projection over the existing invoice and treasury records. No business writes. */
class BillingWorkspace
{
    public function customers(): Builder
    {
        $jsonName = DB::getDriverName() === 'sqlite' ? "json_extract(s.customer_snapshot, '$.company_name')" : "s.customer_snapshot->>'company_name'";
        $first = DB::getDriverName() === 'sqlite' ? "json_extract(s.customer_snapshot, '$.first_name')" : "s.customer_snapshot->>'first_name'";
        $last = DB::getDriverName() === 'sqlite' ? "json_extract(s.customer_snapshot, '$.last_name')" : "s.customer_snapshot->>'last_name'";
        $taxId = DB::getDriverName() === 'sqlite' ? "json_extract(s.customer_snapshot, '$.tax_id')" : "s.customer_snapshot->>'tax_id'";
        $party = "coalesce(nullif($jsonName, ''), nullif(trim(coalesce($first, '') || ' ' || coalesce($last, '')), ''), p.company_name, p.first_name, '')";
        $credits = "coalesce((select sum(c.amount) from sales_documents c where c.source_document_id = s.id and c.type = 'credit_note' and c.status <> 'draft'), 0)";

        return DB::table('sales_documents as s')->leftJoin('people as p', 'p.id', '=', 's.person_id')->leftJoin('installations as i', 'i.id', '=', 's.installation_id')
            ->whereIn('s.type', ['invoice', 'credit_note'])->selectRaw("s.id, 'customer' as kind, s.number, s.type, $party as party, $taxId as tax_id, s.person_id as party_id, i.project_reference as project, s.installation_id, s.issue_date, s.due_date, (s.subtotal - s.discount_amount) as base, s.tax_amount as tax, cast(null as numeric) as withholding, s.amount, s.paid_amount as paid, $credits as credits, s.status, s.locked_at, s.source_document_id");
    }

    public function suppliers(): Builder
    {
        $name = DB::getDriverName() === 'sqlite' ? "json_extract(d.supplier_snapshot, '$.legal_name')" : "d.supplier_snapshot->>'legal_name'";
        $taxId = DB::getDriverName() === 'sqlite' ? "json_extract(d.supplier_snapshot, '$.tax_id')" : "d.supplier_snapshot->>'tax_id'";
        $credits = "coalesce((select sum(c.amount) from purchase_documents c where c.source_document_id = d.id and c.type = 'supplier_credit_note' and c.status <> 'cancelled'), 0)";
        $purchases = DB::table('purchase_documents as d')->leftJoin('suppliers as p', 'p.id', '=', 'd.supplier_id')->leftJoin('installations as i', 'i.id', '=', 'd.installation_id')
            ->whereIn('d.type', ['supplier_invoice', 'supplier_credit_note'])->selectRaw("d.id, 'purchase' as kind, d.number, d.type, coalesce($name, p.legal_name, '') as party, coalesce($taxId, p.tax_id) as tax_id, d.supplier_id as party_id, i.project_reference as project, d.installation_id, d.issue_date, d.due_date, d.subtotal as base, d.tax_amount as tax, cast(null as numeric) as withholding, d.amount, d.paid_amount as paid, $credits as credits, d.status, d.locked_at, d.source_document_id");
        $expenses = DB::table('expenses as e')->leftJoin('suppliers as p', 'p.id', '=', 'e.supplier_id')->leftJoin('installations as i', 'i.id', '=', 'e.installation_id')
            ->whereIn('e.type', ['invoice', 'simplified_invoice'])->where('e.currency_code', 'EUR')->selectRaw("e.id, 'expense' as kind, e.number, e.type, coalesce(e.issuer_legal_name, p.legal_name, e.merchant_name, '') as party, e.issuer_tax_id as tax_id, e.supplier_id as party_id, i.project_reference as project, e.installation_id, e.expense_date as issue_date, e.due_date, e.subtotal as base, e.tax_amount as tax, e.withholding_amount as withholding, e.amount, e.paid_amount as paid, 0 as credits, e.status, null as locked_at, null as source_document_id");

        return DB::query()->fromSub($purchases->unionAll($expenses), 'supplier_records')->select('*');
    }

    public function invoices(string $side): Builder
    {
        $source = $side === 'customers' ? $this->customers() : $this->suppliers();
        $balance = "case when type in ('credit_note', 'supplier_credit_note') or status in ('draft', 'cancelled') then 0 when amount - paid + credits > 0 then amount - paid + credits else 0 end";
        $balances = DB::query()->fromSub($source, 'documents')->selectRaw("*, $balance as balance");

        $states = DB::query()->fromSub($balances, 'balances')->selectRaw("*, case when status = 'draft' then 'draft' when status = 'cancelled' then 'cancelled' when type in ('credit_note', 'supplier_credit_note') then 'credit_note' when balance <= 0 then 'paid' when due_date < ? then 'overdue' when paid > 0 then 'partial' else 'pending' end as payment_state", [today()->toDateString()]);

        return DB::query()->fromSub($states, 'invoice_states')->select('*');
    }

    public function payments(bool $customers = true, bool $suppliers = true): Builder
    {
        $receipts = DB::table('sales_document_payments as m')->join('sales_documents as d', 'd.id', '=', 'm.sales_document_id')->leftJoin('people as p', 'p.id', '=', 'd.person_id')
            ->whereNull('m.voided_at')->where('d.type', 'invoice')->when(! $customers, fn ($q) => $q->whereRaw('1 = 0'))->selectRaw("m.id, 'receipt' as kind, m.paid_at, m.amount, m.method, m.reference, d.number, coalesce(p.company_name, p.first_name, '') as party, d.id as document_id, 'customer' as document_kind");
        $payments = DB::table('supplier_payments as m')->leftJoin('purchase_documents as d', 'd.id', '=', 'm.purchase_document_id')->leftJoin('expenses as e', 'e.id', '=', 'm.expense_id')->leftJoin('suppliers as p', 'p.id', '=', 'd.supplier_id')->leftJoin('suppliers as ep', 'ep.id', '=', 'e.supplier_id')
            ->whereNull('m.voided_at')->when(! $suppliers, fn ($q) => $q->whereRaw('1 = 0'))->selectRaw("m.id, 'payment' as kind, m.paid_at, m.amount, m.method, m.reference, coalesce(d.number, e.number) as number, coalesce(p.legal_name, e.issuer_legal_name, ep.legal_name, e.merchant_name, '') as party, coalesce(d.id, e.id) as document_id, case when d.id is not null then 'purchase' else 'expense' end as document_kind");

        return DB::query()->fromSub($receipts->unionAll($payments), 'movements')->select('*');
    }

    public static function decimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HalfUp);
    }

    public function totals(Builder $query, string $column): string
    {
        // PostgreSQL aggregates NUMERIC in the database; decimal strings cross the API.
        return self::decimal(DB::query()->fromSub($query, 'aggregate_records')->sum($column));
    }

    public function invoice(object $row): array
    {
        return [...(array) $row, ...collect(['base', 'tax', 'amount', 'paid', 'credits', 'balance'])->mapWithKeys(fn ($key) => [$key => self::decimal($row->$key)])->all(),
            'overpaid' => in_array($row->type, ['credit_note', 'supplier_credit_note']) || in_array($row->status, ['draft', 'cancelled']) ? '0.00' : (string) BigDecimal::max('0.00', BigDecimal::of((string) $row->paid)->minus((string) $row->amount)->minus((string) $row->credits))->toScale(2, RoundingMode::HalfUp),
            'withholding' => $row->withholding === null ? null : self::decimal($row->withholding),
            'document_state' => $row->kind === 'customer' ? ($row->status === 'draft' ? 'draft' : 'issued') : ($row->status === 'cancelled' ? 'cancelled' : 'received'),
            'href' => $row->kind === 'customer' ? '/sales/'.$row->id : '/billing/suppliers/'.$row->kind.'/'.$row->id,
        ];
    }

    public function movement(object $row): array
    {
        return [...(array) $row, 'amount' => self::decimal($row->amount), 'href' => $row->document_kind === 'customer' ? '/sales/'.$row->document_id : ($row->document_kind === 'purchase' ? '/billing/suppliers/purchase/'.$row->document_id : '/expenses/'.$row->document_id)];
    }
}
