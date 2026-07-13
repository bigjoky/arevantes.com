<?php

namespace App\Http\Controllers;

use App\Models\TaxReturn;
use App\Models\TaxReturnPayment;
use App\Services\Accounting\JournalPoster;
use App\Services\Tax\VatReport;
use App\Services\Tax\VatSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxController extends Controller
{
    public function index(Request $request, VatReport $report): Response
    {
        [$year, $quarter] = $this->period($request);

        return Inertia::render('tax/index', [...$report->make($year, $quarter), 'taxReturn' => $quarter ? TaxReturn::with(['journalEntry:id,number', 'payments:id,tax_return_id,paid_on,amount,method,reference'])->where(['tax_type' => 'vat', 'year' => $year, 'quarter' => $quarter])->first() : null]);
    }

    public function prepare(Request $request, VatSettlement $settlement): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'quarter' => ['required', 'integer', 'between:1,4']]);
        $settlement->prepare((int) $data['year'], (int) $data['quarter'], $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('VAT return prepared and settlement entry posted.')]);
    }

    public function file(Request $request, TaxReturn $taxReturn): RedirectResponse
    {
        abort_unless($taxReturn->status === 'prepared', 422);
        $data = $request->validate(['filing_reference' => ['required', 'string', 'max:255'], 'filed_on' => ['required', 'date'], 'receipt' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $path = $data['receipt']->store("tax-returns/{$taxReturn->id}", 'local');
        $taxReturn->update(['status' => 'filed', 'filing_reference' => $data['filing_reference'], 'filed_on' => $data['filed_on'], 'filed_by' => $request->user()->id, 'receipt_path' => $path]);

        return back()->with('toast', ['type' => 'success', 'message' => __('VAT return marked as filed.')]);
    }

    public function receipt(TaxReturn $taxReturn): StreamedResponse
    {
        abort_unless($taxReturn->receipt_path && Storage::disk('local')->exists($taxReturn->receipt_path), 404);

        return Storage::disk('local')->download($taxReturn->receipt_path, "vat-{$taxReturn->year}-Q{$taxReturn->quarter}-receipt.pdf", ['Content-Type' => 'application/pdf']);
    }

    public function pay(Request $request, TaxReturn $taxReturn, JournalPoster $poster): RedirectResponse
    {
        abort_unless($taxReturn->status === 'filed' && abs((float) $taxReturn->result) > 0, 422);
        $outstanding = round(abs((float) $taxReturn->result) - (float) $taxReturn->paid_amount, 2);
        $data = $request->validate(['paid_on' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0', 'max:'.$outstanding], 'method' => ['required', Rule::in(['bank_transfer', 'direct_debit', 'cash'])], 'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $taxReturn, $poster, $data) {
            $payment = TaxReturnPayment::create([...$data, 'tax_return_id' => $taxReturn->id, 'created_by' => $request->user()->id]);
            $bank = $data['method'] === 'cash' ? '570' : '572';
            $payable = (float) $taxReturn->result > 0;
            $lines = $payable
                ? [['account_code' => '4750', 'debit' => $data['amount'], 'credit' => 0], ['account_code' => $bank, 'debit' => 0, 'credit' => $data['amount']]]
                : [['account_code' => $bank, 'debit' => $data['amount'], 'credit' => 0], ['account_code' => '4700', 'debit' => 0, 'credit' => $data['amount']]];
            $entry = $poster->post($data['paid_on'], __('VAT :direction :year Q:quarter', ['direction' => $payable ? __('payment') : __('refund'), 'year' => $taxReturn->year, 'quarter' => $taxReturn->quarter]), $lines, $request->user()->id, 'tax_payment', $payment, 'tax_payment');
            $payment->update(['journal_entry_id' => $entry->id]);
            $paid = round((float) $taxReturn->payments()->sum('amount'), 2);
            $taxReturn->update(['paid_amount' => $paid, 'settlement_status' => $paid >= abs((float) $taxReturn->result) ? 'settled' : 'partial']);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Tax settlement movement registered.')]);
    }

    public function export(Request $request, VatReport $report): StreamedResponse
    {
        [$year, $quarter] = $this->period($request);
        $data = $report->make($year, $quarter);
        $book = $request->validate(['book' => ['required', Rule::in(['issued', 'received'])]])['book'];

        return response()->streamDownload(function () use ($data, $book) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['date', 'number', 'tax_id', 'party', 'vat_rate', 'taxable_base', 'vat', 'total'], ';');
            foreach ($data[$book] as $row) {
                fputcsv($out, [$row['date'], $row['number'], $row['tax_id'], $row['party'], number_format($row['rate'], 2, ',', ''), number_format($row['base'], 2, ',', ''), number_format($row['vat'], 2, ',', ''), number_format($row['total'], 2, ',', '')], ';');
            }
            fclose($out);
        }, "vat-book-{$book}-{$year}".($quarter ? "-Q{$quarter}" : '').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function period(Request $request): array
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100'], 'quarter' => ['nullable', 'integer', 'between:1,4']]);

        return [(int) ($data['year'] ?? now()->year), isset($data['quarter']) ? (int) $data['quarter'] : null];
    }
}
