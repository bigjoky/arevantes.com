<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Services\Accounting\SupplierPaymentSourceResolver;
use App\Services\Billing\BillingWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BillingWorkspaceController extends Controller
{
    private function capabilities(): array
    {
        return ['customers' => Gate::allows('manage-installations'), 'suppliers' => Gate::allows('view-finance'), 'payments' => Gate::allows('view-finance'), 'createCustomer' => Gate::allows('manage-installations'), 'createSupplier' => Gate::allows('manage-catalog') && Gate::allows('view-finance'), 'recordReceipt' => Gate::allows('manage-installations') && Gate::allows('manage-treasury'), 'recordPayment' => Gate::allows('manage-purchase-payments'), 'contractualNeeds' => false, 'multiCompany' => false, 'structuredInvoice' => false, 'sif' => false];
    }

    private function context(Request $request): array
    {
        $request->validate(['year' => 'nullable|integer|min:2000|max:2200', 'period' => 'nullable|in:month,quarter,year']);
        $year = FiscalYear::where('year', $request->integer('year', today()->year))->first();
        $from = $year?->starts_on?->copy() ?? Carbon::create($request->integer('year', today()->year), 1, 1);
        $to = $year?->ends_on?->copy() ?? $from->copy()->endOfYear();
        $period = $request->input('period', 'month');
        if ($period !== 'year') {
            $anchor = today()->betweenIncluded($from, $to) ? today() : $to->copy();
            $periodFrom = $period === 'month' ? $anchor->copy()->startOfMonth() : $anchor->copy()->startOfQuarter();
            $periodTo = $period === 'month' ? $anchor->copy()->endOfMonth() : $anchor->copy()->endOfQuarter();
            $from = $periodFrom->greaterThan($from) ? $periodFrom : $from;
            $to = $periodTo->lessThan($to) ? $periodTo : $to;
        }

        return ['year' => $year?->year ?? $request->integer('year', today()->year), 'period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'fiscalAvailable' => $year !== null, 'years' => FiscalYear::orderByDesc('year')->get(['year', 'starts_on', 'ends_on', 'status'])];
    }

    public function dashboard(Request $request, BillingWorkspace $workspace): Response
    {
        abort_unless(Gate::any(['manage-installations', 'view-finance']), 403);
        $caps = $this->capabilities();
        $context = $this->context($request);
        $customer = $workspace->invoices('customers');
        $supplier = $workspace->invoices('suppliers');
        $issued = (clone $customer)->where('status', '<>', 'draft')->whereBetween('issue_date', [$context['from'], $context['to']]);
        $receipts = $workspace->payments($caps['customers'] && $caps['payments'], $caps['suppliers'] && $caps['payments'])->whereBetween('paid_at', [$context['from'], $context['to']]);
        $pendingCustomers = (clone $customer)->where('balance', '>', 0);
        $pendingSuppliers = (clone $supplier)->where('balance', '>', 0);
        $overdue = (clone $pendingCustomers)->where('due_date', '<', today()->toDateString());
        $metrics = [
            'invoiced' => $caps['customers'] ? $workspace->totals(clone $issued, 'amount') : null,
            'receivable' => $caps['customers'] ? $workspace->totals(clone $pendingCustomers, 'balance') : null,
            'payable' => $caps['suppliers'] ? $workspace->totals(clone $pendingSuppliers, 'balance') : null,
            'overdue' => $caps['customers'] ? $workspace->totals(clone $overdue, 'balance') : null,
            'received' => $caps['payments'] && $caps['customers'] ? $workspace->totals((clone $receipts)->where('kind', 'receipt'), 'amount') : null,
            'paid' => $caps['payments'] && $caps['suppliers'] ? $workspace->totals((clone $receipts)->where('kind', 'payment'), 'amount') : null,
        ];
        $counts = ['receivable' => $caps['customers'] ? (clone $pendingCustomers)->count() : null, 'payable' => $caps['suppliers'] ? (clone $pendingSuppliers)->count() : null, 'overdue' => $caps['customers'] ? (clone $overdue)->count() : null];
        $series = [];
        $fiscal = FiscalYear::where('year', $context['year'])->first();
        $start = $fiscal?->starts_on?->copy() ?? Carbon::create($context['year'], 1, 1);
        $end = $fiscal?->ends_on?->copy() ?? $start->copy()->endOfYear();
        $invoiceSeries = $caps['customers'] ? (clone $customer)->where('status', '<>', 'draft')->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])->select([])->selectRaw('substring(cast(issue_date as varchar), 1, 7) as month, sum(amount) as total')->groupByRaw('substring(cast(issue_date as varchar), 1, 7)')->pluck('total', 'month') : collect();
        $receiptSeries = $caps['customers'] && $caps['payments'] ? $workspace->payments(true, false)->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])->select([])->selectRaw('substring(cast(paid_at as varchar), 1, 7) as month, sum(amount) as total')->groupByRaw('substring(cast(paid_at as varchar), 1, 7)')->pluck('total', 'month') : collect();
        for ($month = $start->copy()->startOfMonth(); $month->lte($end); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $series[] = ['month' => $key, 'invoiced' => $caps['customers'] ? BillingWorkspace::decimal($invoiceSeries[$key] ?? '0') : null, 'received' => $caps['customers'] && $caps['payments'] ? BillingWorkspace::decimal($receiptSeries[$key] ?? '0') : null];
        }
        $upcoming = [];
        foreach ([[0, 0, 'Hoy'], [1, 1, 'Mañana'], [2, 7, 'En 7 días'], [8, 30, 'En 30 días']] as [$a, $b, $label]) {
            $from = today()->addDays($a)->toDateString();
            $to = today()->addDays($b)->toDateString();
            $q = (clone $pendingCustomers)->whereBetween('due_date', [$from, $to]);
            $upcoming[] = ['label' => $label, 'count' => $caps['customers'] ? (clone $q)->count() : null, 'amount' => $caps['customers'] ? $workspace->totals($q, 'balance') : null, 'href' => '/sales?due_from='.$from.'&due_to='.$to.'&state=pending'];
        }

        return Inertia::render('billing/dashboard', ['context' => $context, 'capabilities' => $caps, 'metrics' => $metrics, 'counts' => $counts, 'series' => $series, 'upcoming' => $upcoming,
            'states' => $caps['customers'] ? (clone $customer)->whereNotIn('type', ['credit_note'])->where('status', '<>', 'draft')->select('payment_state')->selectRaw('count(*) as count')->groupBy('payment_state')->get() : [],
            'customers' => $caps['customers'] ? (clone $customer)->orderByDesc('issue_date')->orderByDesc('id')->limit(5)->get()->map(fn ($r) => $workspace->invoice($r)) : null,
            'suppliers' => $caps['suppliers'] ? (clone $supplier)->orderByDesc('issue_date')->orderByDesc('id')->limit(5)->get()->map(fn ($r) => $workspace->invoice($r)) : null,
            'movements' => $caps['payments'] ? $workspace->payments($caps['customers'], $caps['suppliers'])->orderByDesc('paid_at')->orderByDesc('id')->limit(4)->get()->map(fn ($r) => $workspace->movement($r)) : null,
            'needs' => null,
        ]);
    }

    public function customers(Request $request, BillingWorkspace $workspace): Response
    {
        abort_unless(Gate::allows('manage-installations'), 403);

        return $this->listing($request, $workspace, 'customers');
    }

    public function suppliers(Request $request, BillingWorkspace $workspace): Response
    {
        abort_unless(Gate::allows('view-finance'), 403);

        return $this->listing($request, $workspace, 'suppliers');
    }

    private function listing(Request $request, BillingWorkspace $workspace, string $side): Response
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'type' => 'nullable|in:invoice,credit_note', 'status' => 'nullable|in:draft,issued,partial,paid,overdue', 'state' => 'nullable|in:draft,issued,paid,partial,pending,overdue,credit_note,cancelled', 'party' => 'nullable|integer|min:1', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'due_from' => 'nullable|date', 'due_to' => 'nullable|date|after_or_equal:due_from', 'sort' => 'nullable|in:issue_date,due_date,number,amount,party', 'direction' => 'nullable|in:asc,desc', 'per_page' => 'nullable|integer|in:10,15,25,50', 'page' => 'nullable|integer|min:1']);
        $query = $workspace->invoices($side);
        if ($side === 'customers' && $request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if (! $request->filled('state') && $request->filled('status')) {
            $request->merge(['state' => $request->input('status')]);
            $filters['state'] = $request->input('status');
        }

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $term)->orWhere('party', 'like', $term)->orWhere('project', 'like', $term)->orWhere('tax_id', 'like', $term));
        }
        if ($request->filled('state')) {
            $state = $request->input('state');
            if ($state === 'issued') {
                $query->where('status', '<>', 'draft')->whereNotIn('type', ['credit_note', 'supplier_credit_note']);
            } elseif ($state === 'pending') {
                $query->where('balance', '>', 0);
            } else {
                $query->where('payment_state', $state);
            }
        }
        foreach (['from' => ['issue_date', '>='], 'to' => ['issue_date', '<='], 'due_from' => ['due_date', '>='], 'due_to' => ['due_date', '<=']] as $key => [$column, $operator]) {
            if ($request->filled($key)) {
                $query->where($column, $operator, $request->input($key));
            }
        }
        if ($request->filled('party')) {
            $query->where('party_id', $request->integer('party'));
        }
        $invoices = $query->orderBy($request->input('sort', 'issue_date'), $request->input('direction', 'desc'))->orderBy('kind')->orderByDesc('id')->paginate($request->integer('per_page', 10))->withQueryString()->through(fn ($r) => $workspace->invoice($r));

        return Inertia::render('billing/invoices', ['side' => $side, 'filters' => $filters, 'invoices' => $invoices, 'parties' => $workspace->invoices($side)->whereNotNull('party_id')->select('party_id', 'party')->distinct()->orderBy('party')->get(), 'capabilities' => $this->capabilities()]);
    }

    public function payments(Request $request, BillingWorkspace $workspace): Response
    {
        abort_unless(Gate::allows('view-finance'), 403);
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'kind' => 'nullable|in:receipt,payment', 'tab' => 'nullable|in:movements,pending,due', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1']);
        $caps = $this->capabilities();
        $q = $workspace->payments($caps['customers'], true);
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $q->where(fn ($q) => $q->where('number', 'like', $term)->orWhere('party', 'like', $term)->orWhere('reference', 'like', $term));
        }
        if ($request->filled('kind')) {
            $q->where('kind', $request->input('kind'));
        }
        if ($request->filled('from')) {
            $q->where('paid_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $q->where('paid_at', '<=', $request->input('to'));
        }
        $movements = $q->orderByDesc('paid_at')->orderBy('kind')->orderByDesc('id')->paginate(10)->withQueryString()->through(fn ($r) => $workspace->movement($r));
        $pending = null;
        if ($request->input('tab', 'movements') !== 'movements') {
            $source = $workspace->invoices('suppliers');
            if ($caps['customers']) {
                $source->unionAll($workspace->invoices('customers'));
            }
            $due = DB::query()->fromSub($source, 'pending_documents')->where('balance', '>', 0);
            if ($request->input('tab') === 'due') {
                $due->whereNotNull('due_date');
            }
            if ($request->filled('search')) {
                $term = '%'.$request->input('search').'%';
                $due->where(fn ($q) => $q->where('number', 'like', $term)->orWhere('party', 'like', $term));
            }
            if ($request->filled('kind')) {
                $request->input('kind') === 'receipt' ? $due->where('kind', 'customer') : $due->where('kind', '<>', 'customer');
            }
            if ($request->filled('from')) {
                $due->where('due_date', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $due->where('due_date', '<=', $request->input('to'));
            }
            $pending = $due->orderBy('due_date')->orderBy('kind')->orderBy('id')->paginate(10)->withQueryString()->through(fn ($r) => $workspace->invoice($r));
        }

        return Inertia::render('billing/payments', ['movements' => $movements, 'pending' => $pending, 'filters' => $filters, 'capabilities' => $caps]);
    }

    public function customerShow(Request $request, SalesDocument $salesDocument, BillingWorkspace $workspace): Response
    {
        abort_unless(Gate::allows('manage-installations'), 403);
        abort_unless(in_array($salesDocument->type, ['invoice', 'credit_note']), 404);
        $record = $workspace->invoices('customers')->where('id', $salesDocument->id)->firstOrFail();
        $salesDocument->load(['lines', 'payments.accountingEntry', 'deliveries', 'installation', 'quote:id,number', 'sourceDocument:id,number,type', 'accountingEntry:id,number,status']);
        $invoice = $workspace->invoice($record);
        $isRecurring = $salesDocument->recurringServiceBilling()->exists() || $salesDocument->sourceDocument?->recurringServiceBilling()->exists();

        $salesDocument->setAttribute('can_issue_invoice', Gate::allows('issue-invoices') && (! $isRecurring || Gate::allows('manage-treasury')));
        $salesDocument->setAttribute('can_prepare_credit_note', Gate::allows('prepare-credit-notes') && (! $isRecurring || Gate::allows('manage-treasury')));

        return Inertia::render('billing/invoice', ['invoice' => $invoice, 'document' => $salesDocument, 'detail' => $salesDocument, 'identity' => $salesDocument->customer_snapshot, 'issuer' => $salesDocument->issuerIdentity(), 'capabilities' => [...$this->capabilities(), 'issue' => Gate::allows('issue-invoices') && (! $isRecurring || Gate::allows('manage-treasury')), 'credit' => Gate::allows('prepare-credit-notes') && (! $isRecurring || Gate::allows('manage-treasury')), 'edit' => $salesDocument->status === 'draft' && ! $salesDocument->locked_at && ! $salesDocument->quote_id && ! $salesDocument->source_document_id && ! $isRecurring], 'operationKey' => (string) Str::uuid(), 'paymentSources' => [], 'managementHref' => '/sales/'.$salesDocument->id.'?view=manage']);
    }

    public function supplierShow(Request $request, string $kind, int $id, BillingWorkspace $workspace): Response
    {
        abort_unless(Gate::allows('view-finance'), 403);
        abort_unless(in_array($kind, ['purchase', 'expense']), 404);
        $record = $workspace->invoices('suppliers')->where('kind', $kind)->where('id', $id)->firstOrFail();
        $model = $kind === 'purchase' ? PurchaseDocument::with(['lines', 'payments', 'supplier', 'installation', 'sourceDocument', 'events', 'accountingEntry'])->findOrFail($id) : Expense::with(['taxLines', 'payments', 'supplier', 'installation', 'attachments', 'accountingEntry'])->findOrFail($id);

        $canCorrect = $request->user()->hasRole('admin') && Gate::allows('manage-catalog');
        $history = $kind === 'expense' && $canCorrect ? AuditLog::where('auditable_type', $model->getMorphClass())->where('auditable_id', $model->id)->with('user:id,name')->latest('id')->limit(50)->get() : collect();
        $legacyExpense = $kind === 'expense' ? (clone $model)->load(['payments' => fn ($query) => $query->withoutGlobalScope('active_supplier_payment')->with('voidedBy:id,name')]) : null;
        $voidedPayments = $legacyExpense?->payments->filter(fn ($payment) => $payment->voided_at)->map(fn ($payment) => $payment->only(['id', 'paid_at', 'amount', 'voided_at', 'void_reason', 'reversal_entry_id']))->values() ?? collect();
        $compatibility = $kind === 'expense' ? [
            'expense' => $legacyExpense, 'canEditExpense' => $canCorrect && $model->status !== 'paid',
            'canCancelPayments' => $request->user()->hasRole('admin') && Gate::allows('manage-purchase-payments'),
            'expenseCorrections' => $history->where('event', 'expense_corrected')->values(),
            'issuerCorrections' => $history->where('event', 'expense_issuer_corrected')->map(fn ($log) => ['id' => $log->id, 'user' => $log->user?->name, 'created_at' => $log->created_at->toIso8601String(), 'before' => $log->old_values, 'after' => $log->new_values])->values(),
        ] : [];

        return Inertia::render('billing/invoice', [...$compatibility, 'voidedPayments' => $voidedPayments, 'auditEvents' => $history->map(fn ($log) => ['id' => $log->id, 'event' => $log->event, 'date' => $log->created_at->toIso8601String(), 'user' => $log->user?->name]), 'invoice' => $workspace->invoice($record), 'detail' => $model, 'identity' => $kind === 'purchase' ? $model->supplierIdentity() : ['legal_name' => $model->issuer_legal_name, 'tax_id' => $model->issuer_tax_id], 'issuer' => null, 'capabilities' => [...$this->capabilities(), 'recordPayment' => Gate::allows('manage-purchase-payments') && ($kind !== 'purchase' || $model->matching_status !== 'review_required'), 'issue' => false, 'credit' => Gate::allows('manage-purchases') && $kind === 'purchase', 'edit' => $kind === 'expense' && $canCorrect && $model->status !== 'paid'], 'operationKey' => (string) Str::uuid(), 'paymentSources' => Gate::allows('manage-purchase-payments') ? app(SupplierPaymentSourceResolver::class)->options() : [], 'originalAvailable' => $kind === 'purchase' && Gate::allows('manage-catalog') && $model->attachment()->exists(), 'managementHref' => $kind === 'purchase' ? '/purchases/'.$id.'?view=manage' : '/expenses/'.$id.'?view=manage']);
    }
}
