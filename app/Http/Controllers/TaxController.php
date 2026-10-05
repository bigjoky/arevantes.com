<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\TaxReturn;
use App\Models\TaxReturnPayment;
use App\Services\Accounting\JournalPoster;
use App\Services\Tax\CompanyCensus;
use App\Services\Tax\ExpenseFiscalReview;
use App\Services\Tax\Model303Preview;
use App\Services\Tax\PurchaseFiscalReview;
use App\Services\Tax\VatReport;
use App\Services\Tax\VatSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxController extends Controller
{
    public function model303(Request $request, VatReport $report, Model303Preview $preview, ExpenseFiscalReview $review): Response
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100'], 'quarter' => ['nullable', 'integer', 'between:1,4']]);
        $year = (int) ($data['year'] ?? now()->year);
        $quarter = (int) ($data['quarter'] ?? now()->quarter);
        $books = $report->make($year, $quarter);
        $documents = SalesDocument::whereIn('id', collect($books['issued'])->pluck('source_id')->unique())
            ->get(['id', 'type', 'customer_snapshot'])->mapWithKeys(fn ($document) => [$document->id => [
                'type' => $document->type, 'country' => $document->customer_snapshot['country'] ?? null,
            ]])->all();

        $classifications = Expense::whereIn('id', collect($books['received'])->where('source_type', 'expense')->pluck('source_id')->unique())
            ->with(['taxLines', 'fiscalClassification'])->get()->mapWithKeys(fn ($expense) => ['expense:'.$expense->id => [
                'current' => $review->isCurrent($expense), 'operation_class' => $expense->fiscalClassification?->operation_class,
            ]])->all();

        $purchaseEvidence = app(PurchaseFiscalReview::class);
        $classifications += PurchaseDocument::whereIn('id', collect($books['received'])->where('source_type', 'purchase_document')->pluck('source_id')->unique())
            ->with(['lines', 'fiscalClassification'])->get()->mapWithKeys(fn ($purchase) => ['purchase_document:'.$purchase->id => [
                'current' => $purchaseEvidence->isCurrent($purchase),
                'operation_class' => $purchase->fiscalClassification?->operation_class,
                'issues' => ['purchase_review_pending', ...$purchaseEvidence->issues($purchase)],
            ]])->all();

        return Inertia::render('tax/model303', ['preview' => $preview->make($books, $documents, $classifications),
            'census' => app(CompanyCensus::class)->check(CompanyProfile::firstOrFail(), $year)]);
    }

    public function index(Request $request, VatReport $report, VatSettlement $settlement): Response
    {
        [$year, $quarter] = $this->period($request);
        $data = $report->make($year, $quarter);
        $taxReturn = $quarter ? TaxReturn::with(['journalEntry:id,number', 'payments:id,tax_return_id,paid_on,amount,method,reference'])->where(['tax_type' => 'vat', 'year' => $year, 'quarter' => $quarter])->first() : null;

        return Inertia::render('tax/index', [
            ...$data,
            'settlementPreview' => $quarter ? $settlement->preview($data) : null,
            'initialSection' => match ($request->route()->getName()) {
                'tax.issued' => 'issued',
                'tax.received' => 'received',
                default => 'summary',
            },
            'canManageTax' => $request->user()->can('manage-tax'),
            'taxReturn' => $taxReturn,
            'settlementReadiness' => $taxReturn && in_array($taxReturn->status, ['draft', 'approved'], true) ? $settlement->readiness($taxReturn, $data) : null,
        ]);
    }

    public function prepare(Request $request, VatSettlement $settlement): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'quarter' => ['required', 'integer', 'between:1,4']]);
        $settlement->prepare((int) $data['year'], (int) $data['quarter'], $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('VAT settlement draft created.')]);
    }

    public function review(Request $request, TaxReturn $taxReturn, VatSettlement $settlement): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'refresh' => ['nullable', 'boolean']]);
        $settlement->review($taxReturn, $request->user()->id, $data['reason'], (bool) ($data['refresh'] ?? false));

        return back()->with('toast', ['type' => 'success', 'message' => __('VAT settlement review saved.')]);
    }

    public function post(Request $request, TaxReturn $taxReturn, VatSettlement $settlement): RedirectResponse
    {
        $settlement->post($taxReturn, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('VAT return prepared and settlement entry posted.')]);
    }

    public function file(Request $request, TaxReturn $taxReturn): RedirectResponse
    {
        abort_unless($taxReturn->status === 'prepared', 422);
        $negative = (float) $taxReturn->result < 0;
        $data = $request->validate([
            'filing_reference' => ['required', 'string', 'max:255'],
            'filed_on' => ['required', 'date', 'after_or_equal:'.$taxReturn->period_to->toDateString()],
            'negative_result_option' => [$negative ? 'required' : 'nullable', Rule::in(['compensate', 'refund'])],
            'receipt' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);
        if ($negative && $data['negative_result_option'] === 'refund' && $taxReturn->quarter !== 4) {
            throw ValidationException::withMessages([
                'negative_result_option' => __('A VAT refund outside the final period requires monthly REDEME treatment, which is not configured in this module.'),
            ]);
        }
        $path = $data['receipt']->store("tax-returns/{$taxReturn->id}", config('filesystems.private'));
        $option = $negative ? $data['negative_result_option'] : ((float) $taxReturn->result > 0 ? 'payable' : 'none');
        $settlementStatus = match ($option) {
            'compensate' => 'carried_forward',
            'refund' => 'pending_refund',
            'none' => 'settled',
            default => 'pending',
        };
        $taxReturn->update([
            'status' => 'filed',
            'settlement_status' => $settlementStatus,
            'result_option' => $option,
            'filing_reference' => $data['filing_reference'],
            'filed_on' => $data['filed_on'],
            'filed_by' => $request->user()->id,
            'receipt_path' => $path,
            'receipt_original_name' => $data['receipt']->getClientOriginalName(),
            'receipt_sha256' => hash('sha256', Storage::disk(config('filesystems.private'))->get($path)),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('VAT return marked as filed.')]);
    }

    public function receipt(TaxReturn $taxReturn): StreamedResponse
    {
        abort_unless($taxReturn->receipt_path && Storage::disk(config('filesystems.private'))->exists($taxReturn->receipt_path), 404);

        return Storage::disk(config('filesystems.private'))->download($taxReturn->receipt_path, "vat-{$taxReturn->year}-Q{$taxReturn->quarter}-receipt.pdf", ['Content-Type' => 'application/pdf']);
    }

    public function pay(Request $request, TaxReturn $taxReturn, JournalPoster $poster): RedirectResponse
    {
        $cashMovement = (float) $taxReturn->result > 0 || ((float) $taxReturn->result < 0 && $taxReturn->result_option === 'refund');
        abort_unless($taxReturn->status === 'filed' && $cashMovement, 422);
        $outstanding = round(abs((float) $taxReturn->result) - (float) $taxReturn->paid_amount, 2);
        $data = $request->validate(['paid_on' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0', 'max:'.$outstanding], 'method' => ['required', Rule::in(['bank_transfer', 'direct_debit', 'cash'])], 'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $taxReturn, $poster, $data) {
            // Route binding may precede another payment; revalidate under the row lock.
            $taxReturn = TaxReturn::query()->lockForUpdate()->findOrFail($taxReturn->id);
            $cashMovement = (float) $taxReturn->result > 0 || ((float) $taxReturn->result < 0 && $taxReturn->result_option === 'refund');
            abort_unless($taxReturn->status === 'filed' && $cashMovement, 422);
            $outstanding = round(abs((float) $taxReturn->result) - (float) $taxReturn->paid_amount, 2);
            $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:'.$outstanding]]);

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
            $headers = ['date', 'number', 'tax_id', 'party', 'vat_rate', 'taxable_base', $book === 'received' ? 'supported_vat' : 'vat', $book === 'received' ? 'base_plus_vat' : 'total'];
            if ($book === 'received') {
                $headers = [...$headers, 'deductible_vat', 'non_deductible_vat'];
            }
            fputcsv($out, $headers, ';');
            foreach ($data[$book] as $row) {
                $values = [$row['date'], $row['number'], $row['tax_id'], $row['party'], number_format($row['rate'], 2, ',', ''), number_format($row['base'], 2, ',', ''), number_format($row['vat'], 2, ',', ''), number_format($row['total'], 2, ',', '')];
                if ($book === 'received') {
                    $values = [...$values, number_format($row['deductible_vat'], 2, ',', ''), number_format($row['non_deductible_vat'], 2, ',', '')];
                }
                fputcsv($out, $values, ';');
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
