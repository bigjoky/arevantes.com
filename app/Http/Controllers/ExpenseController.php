<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\Installation;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Rules\SafeUpload;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\SupplierPaymentSourceResolver;
use App\Services\Documents\DocumentNumber;
use App\Services\Finance\OutstandingBalanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExpenseController extends Controller
{
    public function __construct(private OutstandingBalanceCalculator $balances) {}

    public function index(): Response
    {
        return Inertia::render('expenses/index', [
            'expenses' => Expense::with(['category:id,name', 'supplier:id,legal_name,trade_name'])->latest('expense_date')->get(),
            'supplierInvoices' => PurchaseDocument::with('supplier:id,legal_name,trade_name')->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->latest('issue_date')->get(),
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name', 'tax_id']),
            'installations' => Installation::orderByDesc('created_at')->get(['id', 'project_reference', 'site_name']),
            'summary' => ['pending' => $this->pendingTotal(), 'paidThisMonth' => SupplierPayment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), 'expensesThisMonth' => Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')],
        ]);
    }

    public function show(Expense $expense, SupplierPaymentSourceResolver $sources): Response
    {
        $expense->load(['category', 'supplier', 'attachments', 'payments.ledgerAccount', 'payments.fundingBankAccount', 'payments.fundingCard', 'payments.fundingEmployee', 'taxLines']);
        $expense->payments->each(fn (SupplierPayment $payment) => $payment->setAttribute('source_label', $payment->sourceLabel()));

        $canCorrect = request()->user()->hasRole('admin') && request()->user()->can('manage-catalog');

        return Inertia::render('expenses/show', [
            'expense' => $expense, 'paymentSources' => $sources->options(),
            'canCorrectIssuer' => $canCorrect,
            'issuerRevision' => $canCorrect ? $this->issuerRevision($expense) : null,
            'issuerCorrections' => $canCorrect ? AuditLog::query()
                ->where('auditable_type', $expense->getMorphClass())->where('auditable_id', $expense->id)
                ->where('event', 'expense_issuer_corrected')->with('user:id,name')->latest('id')->limit(50)->get()
                ->map(fn (AuditLog $log) => ['id' => $log->id, 'user' => $log->user?->name, 'created_at' => $log->created_at->toIso8601String(), 'before' => $log->old_values, 'after' => $log->new_values]) : [],
        ]);
    }

    public function correctIssuer(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate([
            'issuer_legal_name' => ['required', 'string', 'max:255'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['required', 'string', 'max:2000'],
            'issuer_revision' => ['required', 'string', 'size:64'],
        ]);

        DB::transaction(function () use ($request, $expense, $data): void {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);
            $before = $expense->only(['issuer_legal_name', 'merchant_name']);
            $after = ['issuer_legal_name' => $data['issuer_legal_name'], 'merchant_name' => $data['merchant_name'] ?? null];
            if ($before === $after) {
                return;
            }
            if (! hash_equals($this->issuerRevision($expense), $data['issuer_revision'])) {
                throw ValidationException::withMessages(['issuer_legal_name' => __('The supplier names have changed. Reload the expense before correcting them.')]);
            }
            // Names are documentary metadata. Never alter the supplier link, tax ID,
            // invoice amounts, journal entries, payments or filed-return snapshots here.
            $expense->update($after);
            AuditLog::create([
                'user_id' => $request->user()->id, 'event' => 'expense_issuer_corrected',
                'auditable_type' => $expense->getMorphClass(), 'auditable_id' => $expense->id,
                'old_values' => $before, 'new_values' => [...$after, 'correction_reason' => $data['correction_reason']],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(), 'method' => $request->method(),
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Expense supplier names corrected.')]);
    }

    private function issuerRevision(Expense $expense): string
    {
        return hash('sha256', json_encode($expense->only(['issuer_legal_name', 'merchant_name']), JSON_THROW_ON_ERROR));
    }

    public function store(Request $request, AccountingSynchronizer $accounting, DocumentNumber $numbers): RedirectResponse
    {
        $request->merge([
            'currency_code' => $request->input('currency_code', 'EUR'),
            'tax_regime' => $request->input('tax_regime', 'standard'),
            'deductible_expense_percent' => $request->input('deductible_expense_percent', 100),
            'withholding_rate' => $request->input('withholding_rate', 0),
            'withholding_amount' => $request->input('withholding_amount', 0),
        ]);
        if ($request->input('supplier_id') === 'none') {
            $request->merge(['supplier_id' => null]);
        }
        if ($request->input('installation_id') === 'none') {
            $request->merge(['installation_id' => null]);
        }

        if ($request->filled('supplier_id')) {
            $supplier = Supplier::find($request->integer('supplier_id'));
            $request->merge([
                'issuer_legal_name' => $request->input('issuer_legal_name') ?: $supplier?->legal_name,
                'issuer_tax_id' => $request->input('issuer_tax_id') ?: $supplier?->tax_id,
                'merchant_name' => $request->input('merchant_name') ?: $supplier?->trade_name ?: $supplier?->legal_name,
            ]);
        }

        $data = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'installation_id' => ['nullable', 'exists:installations,id'],
            'type' => ['required', Rule::in(['receipt', 'simplified_invoice', 'invoice', 'other'])],
            'document_number' => [Rule::requiredIf(fn () => $request->input('type') === 'invoice'), 'nullable', 'string', 'max:255'],
            'invoice_series' => ['nullable', 'string', 'max:64'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'issuer_legal_name' => [Rule::requiredIf(fn () => $request->input('type') === 'invoice'), 'nullable', 'string', 'max:255'],
            'issuer_tax_id' => [Rule::requiredIf(fn () => $request->input('type') === 'invoice'), 'nullable', 'string', 'max:32'],
            'expense_date' => ['required', 'date'],
            'operation_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:expense_date'],
            'currency_code' => ['required', 'string', 'size:3'],
            'tax_regime' => ['required', Rule::in(['standard', 'exempt', 'non_subject', 'reverse_charge', 'intra_community', 'import'])],
            'deductible_expense_percent' => ['required', 'numeric', 'between:0,100'],
            'withholding_rate' => ['nullable', 'numeric', 'between:0,100'],
            'withholding_amount' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['required_without:tax_lines', 'nullable', 'numeric', 'min:0'],
            'tax_amount' => ['required_without:tax_lines', 'nullable', 'numeric', 'min:0'],
            'tax_lines' => ['nullable', 'array', 'max:20'],
            'tax_lines.*.kind' => ['required', Rule::in(['vat', 'equivalence_surcharge'])],
            'tax_lines.*.taxable_base' => ['required', 'numeric', 'min:0'],
            'tax_lines.*.tax_rate' => ['required', 'numeric', 'between:0,100'],
            'tax_lines.*.tax_amount' => ['required', 'numeric', 'min:0'],
            'tax_lines.*.deductible_percent' => ['required', 'numeric', 'between:0,100'],
            'tax_lines.*.exemption_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'webp'], 10240)],
        ]);

        $taxLines = collect($data['tax_lines'] ?? [
            ['kind' => 'vat', 'taxable_base' => $data['subtotal'], 'tax_rate' => (float) $data['subtotal'] > 0 ? (float) $data['tax_amount'] / (float) $data['subtotal'] * 100 : 0, 'tax_amount' => $data['tax_amount'], 'deductible_percent' => 100],
        ])->values()->map(function (array $line, int $index) {
            $expectedTax = round((float) $line['taxable_base'] * (float) $line['tax_rate'] / 100, 2);
            if (abs($expectedTax - (float) $line['tax_amount']) > 0.02) {
                throw ValidationException::withMessages(["tax_lines.{$index}.tax_amount" => __('The tax amount does not match its taxable base and rate.')]);
            }

            $deductiblePercent = $line['kind'] === 'equivalence_surcharge' ? 0 : (float) $line['deductible_percent'];

            return [...$line, 'position' => $index + 1, 'deductible_percent' => $deductiblePercent, 'deductible_amount' => round((float) $line['tax_amount'] * $deductiblePercent / 100, 2)];
        });

        $subtotal = round((float) $taxLines->where('kind', 'vat')->sum('taxable_base'), 2);
        $taxAmount = round((float) $taxLines->sum('tax_amount'), 2);
        $withholdingAmount = round((float) ($data['withholding_amount'] ?? 0), 2);
        $calculatedWithholding = round($subtotal * (float) ($data['withholding_rate'] ?? 0) / 100, 2);
        if (abs($calculatedWithholding - $withholdingAmount) > 0.02) {
            throw ValidationException::withMessages(['withholding_amount' => __('The withholding amount does not match its taxable base and rate.')]);
        }

        if ($data['issuer_tax_id'] ?? null) {
            $duplicate = Expense::where('issuer_tax_id', strtoupper(trim($data['issuer_tax_id'])))
                ->where('document_number', $data['document_number'] ?? null)->exists();
            if ($duplicate && filled($data['document_number'] ?? null)) {
                throw ValidationException::withMessages(['document_number' => __('This issuer invoice is already registered.')]);
            }
        }

        $files = $data['attachments'] ?? [];
        unset($data['attachments'], $data['tax_lines']);
        $data['issuer_tax_id'] = filled($data['issuer_tax_id'] ?? null) ? strtoupper(trim($data['issuer_tax_id'])) : null;
        $data['currency_code'] = strtoupper($data['currency_code']);
        $data['subtotal'] = $subtotal;
        $data['tax_amount'] = $taxAmount;
        $data['withholding_amount'] = $withholdingAmount;
        $storedFiles = [];

        try {
            $directory = 'expenses/'.Str::uuid();
            foreach ($files as $file) {
                $path = $file->store($directory, config('filesystems.private'));
                if (! is_string($path) || $path === '') {
                    throw ValidationException::withMessages(['attachments' => __('The expense document could not be stored. Please try again.')]);
                }
                $storedFiles[] = ['original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()];
            }

            $expense = DB::transaction(function () use ($request, $data, $taxLines, $storedFiles, $accounting, $numbers) {
                $number = $numbers->next('expense', $data['expense_date'])['number'];
                $amount = round((float) $data['subtotal'] + (float) $data['tax_amount'] - (float) $data['withholding_amount'], 2);
                $expense = Expense::create([...$data, 'created_by' => $request->user()->id, 'number' => $number, 'amount' => $amount, 'status' => 'pending']);
                $expense->taxLines()->createMany($taxLines->all());
                foreach ($storedFiles as $storedFile) {
                    $expense->attachments()->create([...$storedFile, 'uploaded_by' => $request->user()->id]);
                }
                $accounting->syncExpense($expense, $request->user()->id);

                return $expense;
            });
        } catch (Throwable $exception) {
            Storage::disk(config('filesystems.private'))->delete(array_column($storedFiles, 'path'));

            throw $exception;
        }

        return to_route('expenses.show', $expense)->with('toast', ['type' => 'success', 'message' => __('Expense created.')]);
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        abort_if($expense->payments()->exists(), 422, __('Expenses with payments cannot be deleted.'));
        abort_if($expense->accountingEntry()->exists(), 422, __('Posted expenses cannot be deleted. Reverse the accounting entry first.'));
        foreach ($expense->attachments as $attachment) {
            Storage::disk(config('filesystems.private'))->delete($attachment->path);
        }
        $expense->delete();

        return to_route('expenses.index')->with('toast', ['type' => 'success', 'message' => __('Expense deleted.')]);
    }

    public function download(Expense $expense, ExpenseAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->expense_id === $expense->id && Storage::disk(config('filesystems.private'))->exists($attachment->path), 404);

        return Storage::disk(config('filesystems.private'))->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    public function destroyAttachment(Expense $expense, ExpenseAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->expense_id === $expense->id, 404);
        Storage::disk(config('filesystems.private'))->delete($attachment->path);
        $attachment->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Attachment deleted.')]);
    }

    public function payExpense(Request $request, Expense $expense, AccountingSynchronizer $accounting): RedirectResponse
    {
        $this->storePayment($request, $expense, null, $accounting);

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment registered.')]);
    }

    public function paySupplierInvoice(Request $request, PurchaseDocument $purchaseDocument, AccountingSynchronizer $accounting): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'supplier_invoice', 422);
        abort_if($purchaseDocument->matching_status === 'review_required', 422, __('Approve invoice variances before registering a payment.'));
        $this->storePayment($request, null, $purchaseDocument, $accounting);

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment registered.')]);
    }

    public function destroyPayment(SupplierPayment $payment): RedirectResponse
    {
        if ($payment->accountingEntry()->exists()) {
            throw ValidationException::withMessages(['payment' => __('Posted payments cannot be deleted. Reverse the accounting entry first.')]);
        }
        $expense = $payment->expense;
        $invoice = $payment->purchaseDocument;
        $payment->delete();
        $this->refreshStatus($expense, $invoice);

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment removed.')]);
    }

    private function storePayment(Request $request, ?Expense $expense, ?PurchaseDocument $invoice, AccountingSynchronizer $accounting): void
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['bank_transfer', 'card', 'cash', 'direct_debit', 'employee_funds', 'other'])],
            'funding_source_type' => ['required', Rule::in(['bank_account', 'card', 'employee', 'ledger_account'])],
            'funding_source_id' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $data = [...$data, ...app(SupplierPaymentSourceResolver::class)->resolve($data['method'], $data['funding_source_type'], (int) $data['funding_source_id'])];
        DB::transaction(function () use ($request, $expense, $invoice, $accounting, $data) {
            $lockedExpense = $expense ? Expense::lockForUpdate()->findOrFail($expense->id) : null;
            $lockedInvoice = $invoice ? PurchaseDocument::lockForUpdate()->findOrFail($invoice->id) : null;

            abort_if($lockedInvoice?->matching_status === 'review_required', 422, __('Approve invoice variances before registering a payment.'));

            $outstanding = $lockedInvoice
                ? $this->balances->purchase($lockedInvoice)
                : $this->balances->expense($lockedExpense);

            if ($outstanding <= 0 || (float) $data['amount'] > $outstanding + 0.0005) {
                throw ValidationException::withMessages([
                    'amount' => __('The payment exceeds the outstanding document balance.'),
                ]);
            }

            $payment = SupplierPayment::create([...$data, 'expense_id' => $lockedExpense?->id, 'purchase_document_id' => $lockedInvoice?->id, 'created_by' => $request->user()->id]);
            $accounting->syncSupplierPayment($payment, $request->user()->id);
            $this->refreshStatus($lockedExpense, $lockedInvoice);
        });
    }

    private function refreshStatus(?Expense $expense, ?PurchaseDocument $invoice): void
    {
        $payable = $expense ?? $invoice;
        $paid = (float) SupplierPayment::when($expense, fn ($q) => $q->where('expense_id', $expense->id))->when($invoice, fn ($q) => $q->where('purchase_document_id', $invoice->id))->sum('amount');
        $netAmount = (float) $payable->amount - ($invoice ? abs((float) $invoice->creditNotes()->sum('amount')) : 0);
        $status = $paid >= $netAmount ? 'paid' : ($paid > 0 ? 'partial' : ($payable->due_date?->isPast() ? 'overdue' : 'pending'));
        $payable->update(['paid_amount' => $paid, 'status' => $status]);
    }

    private function pendingTotal(): float
    {
        return (float) Expense::selectRaw('coalesce(sum(amount - paid_amount), 0) as total')->value('total') + (float) PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required')->selectRaw('coalesce(sum(amount - paid_amount), 0) as total')->value('total');
    }
}
