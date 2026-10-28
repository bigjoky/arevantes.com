<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Services\Billing\BillingWorkspace;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only financial projection. Bank balances never include an invoice or a forecast. */
class FinanceWorkspace
{
    public function __construct(private BillingWorkspace $billing) {}

    public function accounts(?int $selected = null): array
    {
        return BankAccount::query()->when($selected, fn ($q) => $q->whereKey($selected))
            ->withSum(['transactions as movement_total' => fn ($q) => $q->whereDate('booked_on', '<=', today())->whereColumn('bank_transactions.currency', 'bank_accounts.currency')], 'amount')
            ->withMax(['transactions as latest_operation' => fn ($q) => $q->whereDate('booked_on', '<=', today())], 'booked_on')
            ->withSum(['transactions as pending_total' => fn ($q) => $q->where('status', 'pending')->whereDate('booked_on', '<=', today())->whereColumn('bank_transactions.currency', 'bank_accounts.currency')], 'amount')
            ->orderByDesc('is_active')->orderBy('name')->get()->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'bank_name' => $a->bank_name, 'currency' => $a->currency, 'is_active' => $a->is_active,
                'iban_masked' => $a->iban ? '•••• '.substr(preg_replace('/\s+/', '', $a->iban), -4) : null,
                'balance' => BillingWorkspace::decimal(BigDecimal::of($a->opening_balance)->plus((string) ($a->movement_total ?? 0))),
                'pending' => BillingWorkspace::decimal($a->pending_total), 'updated_on' => $a->latest_operation,
                'available' => null, 'href' => '/banking?account='.$a->id,
            ])->all();
    }

    public function bankMovements(?int $account = null, bool $externalOnly = false): Builder
    {
        return DB::table('bank_transactions as t')->join('bank_accounts as a', 'a.id', '=', 't.bank_account_id')
            ->where('t.currency', 'EUR')->where('a.currency', 'EUR')->when($account, fn ($q) => $q->where('a.id', $account))
            ->when($externalOnly && ! $account, fn ($q) => $q->whereNotExists(fn ($q) => $q->selectRaw('1')->from('bank_transfers as bt')->whereNull('bt.reversed_at')->where(fn ($q) => $q->whereColumn('bt.outgoing_transaction_id', 't.id')->orWhereColumn('bt.incoming_transaction_id', 't.id'))))
            ->selectRaw("t.id, 'bank' as source, t.booked_on as date, t.value_on, t.description, t.reference, t.amount, t.currency, a.name as account, a.id as account_id, t.status, coalesce((select fc.name from bank_transaction_postings bp join ledger_accounts la on la.id=bp.counterpart_account_id join financial_categories fc on fc.id=la.financial_category_id where bp.bank_transaction_id=t.id and bp.reversed_at is null and bp.allocation_data is null order by bp.id desc limit 1), 'Sin clasificar') as category");
    }

    /** Payments awaiting reconciliation stay visible separately; they do not change recorded bank balances. */
    public function unmatchedPayments(): Builder
    {
        $allocation = fn (string $field) => DB::table('bank_reconciliations as r')->join('bank_transactions as t', 't.id', '=', 'r.bank_transaction_id')->whereNull('r.reversed_at')->whereNotNull('r.'.$field)->selectRaw('r.'.$field.' as payment_id, sum(coalesce(r.allocated_amount,abs(t.amount))) as allocated')->groupBy('r.'.$field);
        $customers = DB::table('sales_document_payments as p')->whereNull('p.voided_at')->join('sales_documents as d', 'd.id', '=', 'p.sales_document_id')->leftJoinSub($allocation('sales_document_payment_id'), 'matched', 'matched.payment_id', '=', 'p.id')
            ->whereRaw('p.amount > coalesce(matched.allocated,0)')
            ->selectRaw("p.id, 'customer' as source, p.paid_at as date, cast(null as date) as value_on, d.number as description, p.reference, (p.amount-coalesce(matched.allocated,0)) as amount, 'EUR' as currency, 'Sin cuenta bancaria asignada' as account, cast(null as bigint) as account_id, 'unmatched' as status, 'Cobros de clientes' as category, cast(null as bigint) as bank_account_id, p.method");
        $suppliers = DB::table('supplier_payments as p')->leftJoin('purchase_documents as d', 'd.id', '=', 'p.purchase_document_id')->leftJoin('expenses as e', 'e.id', '=', 'p.expense_id')->leftJoinSub($allocation('supplier_payment_id'), 'matched', 'matched.payment_id', '=', 'p.id')->leftJoin('bank_accounts as funding_bank', fn ($join) => $join->on('funding_bank.id', '=', 'p.funding_source_id')->where('p.funding_source_type', 'bank_account'))
            ->whereNull('p.voided_at')->where(fn ($q) => $q->whereNull('e.id')->orWhere('e.currency_code', 'EUR'))->whereRaw('p.amount > coalesce(matched.allocated,0)')
            ->selectRaw("p.id, 'supplier' as source, p.paid_at as date, cast(null as date) as value_on, coalesce(d.number,e.number) as description, p.reference, -(p.amount-coalesce(matched.allocated,0)) as amount, 'EUR' as currency, coalesce(funding_bank.name,'Sin cuenta bancaria asignada') as account, cast(null as bigint) as account_id, 'unmatched' as status, 'Pagos a proveedores' as category, case when p.funding_source_type='bank_account' then p.funding_source_id else null end as bank_account_id, p.method");

        return DB::query()->fromSub($customers->unionAll($suppliers), 'unmatched_payments')->select('*');
    }

    public function obligations(): Builder
    {
        return DB::query()->fromSub($this->billing->invoices('suppliers')->unionAll($this->billing->invoices('customers')), 'obligations')->where('balance', '>', 0);
    }

    public function totals(Builder $q): array
    {
        $r = DB::query()->fromSub($q, 'cash_totals')->selectRaw('coalesce(sum(case when amount>0 then amount else 0 end),0) as incoming, coalesce(sum(case when amount<0 then -amount else 0 end),0) as outgoing, coalesce(sum(amount),0) as net')->first();

        return ['in' => BillingWorkspace::decimal($r->incoming), 'out' => BillingWorkspace::decimal($r->outgoing), 'net' => BillingWorkspace::decimal($r->net)];
    }

    public function series(int $months, ?int $account = null): array
    {
        $start = today()->startOfMonth()->subMonths($months - 1);
        $rows = $this->bankMovements($account, true)->whereDate('t.booked_on', '>=', $start->toDateString())->whereDate('t.booked_on', '<=', today()->toDateString())->select([])
            ->selectRaw('substring(cast(t.booked_on as varchar),1,7) as month, sum(case when t.amount>0 then t.amount else 0 end) as incoming, sum(case when t.amount<0 then -t.amount else 0 end) as outgoing, sum(t.amount) as net')->groupByRaw('substring(cast(t.booked_on as varchar),1,7)')->get()->keyBy('month');

        return collect(range(0, $months - 1))->map(function ($offset) use ($start, $rows) {
            $date = $start->copy()->addMonths($offset);
            $r = $rows->get($date->format('Y-m'));

            return ['month' => $date->format('Y-m'), 'in' => BillingWorkspace::decimal($r?->incoming), 'out' => BillingWorkspace::decimal($r?->outgoing), 'net' => BillingWorkspace::decimal($r?->net)];
        })->all();
    }

    public function movement(object $r): array
    {
        return [...(array) $r, 'amount' => BillingWorkspace::decimal($r->amount), 'href' => $r->source === 'bank' ? '/banking?account='.$r->account_id.'&search='.urlencode($r->reference ?: $r->description) : '/billing/payments?search='.urlencode($r->description)];
    }
}
