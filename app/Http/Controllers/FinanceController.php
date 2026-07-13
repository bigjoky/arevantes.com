<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\StockMovementLine;
use App\Models\SupplierPayment;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $this->year($request);
        $customerPayments = SalesDocumentPayment::with('document:id,number,person_id,installation_id')->whereYear('paid_at', $year)->get();
        $supplierPayments = SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereYear('paid_at', $year)->get();
        $sales = SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereYear('issue_date', $year)->get();
        $purchases = PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required')->whereYear('issue_date', $year)->get();
        $expenses = Expense::whereYear('expense_date', $year)->get();

        $cashFlow = collect(range(1, 12))->map(function ($month) use ($customerPayments, $supplierPayments) {
            $in = $customerPayments->filter(fn ($payment) => $payment->paid_at->month === $month)->sum('amount');
            $out = $supplierPayments->filter(fn ($payment) => $payment->paid_at->month === $month)->sum('amount');

            return ['month' => now()->month($month)->translatedFormat('M'), 'in' => round((float) $in, 2), 'out' => round((float) $out, 2), 'net' => round((float) $in - (float) $out, 2)];
        });

        $receivables = SalesDocument::with('customer:id,first_name,last_name,company_name')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->get();
        $payableInvoices = PurchaseDocument::with('supplier:id,legal_name,trade_name')->withSum('creditNotes as credited_total', 'amount')->where('type', 'supplier_invoice')->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])->get();
        $payableExpenses = Expense::with('supplier:id,legal_name,trade_name')->whereIn('status', ['pending', 'partial', 'overdue'])->get();

        return Inertia::render('finance/index', [
            'year' => $year,
            'summary' => [
                'cashIn' => round((float) $customerPayments->sum('amount'), 2),
                'cashOut' => round((float) $supplierPayments->sum('amount'), 2),
                'receivable' => round((float) $receivables->sum(fn ($item) => (float) $item->amount - (float) $item->paid_amount), 2),
                'payable' => round((float) $payableInvoices->sum(fn ($item) => (float) $item->amount + (float) $item->credited_total - (float) $item->paid_amount) + (float) $payableExpenses->sum(fn ($item) => (float) $item->amount - (float) $item->paid_amount), 2),
                'sales' => round((float) $sales->sum('amount'), 2),
            ],
            'cashFlow' => $cashFlow,
            'vat' => ['output' => round((float) $sales->sum('tax_amount'), 2), 'input' => round((float) $purchases->sum('tax_amount') + (float) $expenses->sum('tax_amount'), 2)],
            'receivables' => $receivables->map(fn ($item) => ['id' => $item->id, 'number' => $item->number, 'party' => $item->customer?->company_name ?: trim(($item->customer?->first_name ?? '').' '.($item->customer?->last_name ?? '')), 'due_date' => $item->due_date?->toDateString(), 'status' => $item->status, 'outstanding' => round((float) $item->amount - (float) $item->paid_amount, 2)]),
            'payables' => $this->payables($payableInvoices, $payableExpenses),
            'profitability' => $this->profitability($year),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        $type = $request->validate(['type' => ['required', 'in:cash,vat']])['type'];

        return response()->streamDownload(function () use ($year, $type) {
            $out = fopen('php://output', 'w');
            if ($type === 'cash') {
                fputcsv($out, ['date', 'direction', 'document', 'method', 'reference', 'amount']);
                SalesDocumentPayment::with('document:id,number')->whereYear('paid_at', $year)->orderBy('paid_at')->each(fn ($p) => fputcsv($out, [$p->paid_at->format('Y-m-d'), 'in', $p->document?->number, $p->method, $p->reference, $p->amount]));
                SupplierPayment::with(['expense:id,number', 'purchaseDocument:id,number'])->whereYear('paid_at', $year)->orderBy('paid_at')->each(fn ($p) => fputcsv($out, [$p->paid_at->format('Y-m-d'), 'out', $p->expense?->number ?? $p->purchaseDocument?->number, $p->method, $p->reference, $p->amount]));
            } else {
                fputcsv($out, ['date', 'direction', 'document', 'tax_base', 'vat', 'total']);
                SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNotNull('locked_at')->whereYear('issue_date', $year)->orderBy('issue_date')->each(fn ($d) => fputcsv($out, [$d->issue_date->format('Y-m-d'), 'output', $d->number, $d->subtotal - $d->discount_amount, $d->tax_amount, $d->amount]));
                PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required')->whereYear('issue_date', $year)->orderBy('issue_date')->each(fn ($d) => fputcsv($out, [$d->issue_date->format('Y-m-d'), 'input', $d->supplier_number ?: $d->number, $d->subtotal, $d->tax_amount, $d->amount]));
                Expense::whereYear('expense_date', $year)->orderBy('expense_date')->each(fn ($d) => fputcsv($out, [$d->expense_date->format('Y-m-d'), 'input', $d->document_number ?: $d->number, $d->subtotal, $d->tax_amount, $d->amount]));
            }
            fclose($out);
        }, "finance-{$type}-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function year(Request $request): int
    {
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100']]);

        return (int) ($validated['year'] ?? now()->year);
    }

    private function payables(Collection $invoices, Collection $expenses): Collection
    {
        return $invoices->map(fn ($item) => ['kind' => 'invoice', 'id' => $item->id, 'number' => $item->number, 'party' => $item->supplier?->trade_name ?: $item->supplier?->legal_name, 'due_date' => $item->due_date?->toDateString(), 'status' => $item->status, 'outstanding' => round((float) $item->amount + (float) $item->credited_total - (float) $item->paid_amount, 2)])->concat($expenses->map(fn ($item) => ['kind' => 'expense', 'id' => $item->id, 'number' => $item->number, 'party' => $item->supplier?->trade_name ?: ($item->supplier?->legal_name ?: $item->merchant_name), 'due_date' => $item->due_date?->toDateString(), 'status' => $item->status, 'outstanding' => round((float) $item->amount - (float) $item->paid_amount, 2)]))->sortBy('due_date')->values();
    }

    private function profitability(int $year): Collection
    {
        $revenue = SalesDocument::with('installation:id,project_reference,site_name')->where('type', 'invoice')->whereNotNull('locked_at')->whereNotNull('installation_id')->whereYear('issue_date', $year)->get()->groupBy('installation_id')->map(fn ($items) => ['installation' => $items->first()->installation, 'revenue' => (float) $items->sum('subtotal') - (float) $items->sum('discount_amount')]);
        $costs = StockMovementLine::whereHas('movement', fn ($q) => $q->whereNotNull('installation_id')->whereYear('posted_at', $year))->with('movement:id,installation_id')->get()->filter(fn ($line) => (float) $line->quantity < 0)->groupBy(fn ($line) => $line->movement->installation_id)->map(fn ($items) => $items->sum(fn ($line) => abs((float) $line->quantity) * (float) ($line->unit_cost ?? 0)));
        $labour = TimeEntry::whereNotNull('ended_at')->whereYear('started_at', $year)->whereHas('workOrder', fn ($q) => $q->whereNotNull('installation_id'))->with('workOrder:id,installation_id')->get()->groupBy(fn ($entry) => $entry->workOrder->installation_id)->map(fn ($entries) => $entries->sum(fn ($entry) => $entry->cost));

        return $revenue->map(function ($item, $id) use ($costs, $labour) {
            $material = (float) ($costs[$id] ?? 0);
            $work = (float) ($labour[$id] ?? 0);

            return ['installation_id' => (int) $id, 'reference' => $item['installation']?->project_reference ?: $item['installation']?->site_name, 'revenue' => round($item['revenue'], 2), 'tracked_cost' => round($material, 2), 'labour_cost' => round($work, 2), 'margin' => round($item['revenue'] - $material - $work, 2)];
        })->sortByDesc('margin')->values();
    }
}
