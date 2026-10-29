<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\Installation;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Rules\SafeUpload;
use App\Services\Accounting\AccountingOperationInput;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\ExpenseCorrection;
use App\Services\Accounting\ExpenseCreditorRegistry;
use App\Services\Accounting\ExpensePaymentCancellation;
use App\Services\Accounting\SupplierPaymentSourceResolver;
use App\Services\Billing\BillingWorkspace;
use App\Services\Documents\DocumentNumber;
use App\Services\Finance\OutstandingBalanceCalculator;
use App\Services\Finance\ReceivedInvoiceIdentity;
use App\Services\Tax\ExpenseDocumentaryCalculation;
use App\Services\Tax\ExpenseDocumentaryReview;
use App\Services\Tax\SupplierInvoiceCalculator;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
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
            'canUseGroupedCalculation' => config('fiscal.grouped_expense_calculation') && request()->user()->hasRole('admin') && request()->user()->can('manage-catalog'),
            'supplierInvoices' => PurchaseDocument::with('supplier:id,legal_name,trade_name')->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->latest('issue_date')->get(),
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'countries' => Country::orderBy('name')->get(['code', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name', 'tax_id']),
            'installations' => Installation::orderByDesc('created_at')->get(['id', 'project_reference', 'site_name']),
            'summary' => ['pending' => $this->pendingTotal(), 'paidThisMonth' => SupplierPayment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), 'expensesThisMonth' => Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')],
        ]);
    }

    public function show(Request $request, Expense $expense, SupplierPaymentSourceResolver $sources): Response
    {
        if ($request->input('view') !== 'manage' && in_array($expense->type, ['invoice', 'simplified_invoice'], true) && $expense->currency_code === 'EUR') {
            return app(BillingWorkspaceController::class)->supplierShow($request, 'expense', $expense->id, app(BillingWorkspace::class));
        }

        $expense->load(['category', 'supplier', 'attachments', 'taxLines']);
        $expense->load(['payments' => fn ($query) => $query->withoutGlobalScope('active_supplier_payment')->with(['ledgerAccount', 'fundingBankAccount', 'fundingCard', 'fundingEmployee', 'voidedBy:id,name', 'reconciliation:id,supplier_payment_id'])]);
        $expense->payments->each(fn (SupplierPayment $payment) => $payment->setAttribute('source_label', $payment->sourceLabel()));

        $canCorrect = request()->user()->hasRole('admin') && request()->user()->can('manage-catalog');

        return Inertia::render('expenses/show', [
            'expense' => $expense, 'paymentSources' => $sources->options(),
            'canEditExpense' => $canCorrect && $expense->status !== 'paid',
            'expenseCorrections' => $canCorrect ? AuditLog::where('auditable_type', $expense->getMorphClass())->where('auditable_id', $expense->id)->where('event', 'expense_corrected')->with('user:id,name')->latest('id')->limit(50)->get() : [],
            'canCancelPayments' => request()->user()->hasRole('admin') && request()->user()->can('manage-purchase-payments'),
            'issuerCorrections' => $canCorrect ? AuditLog::query()
                ->where('auditable_type', $expense->getMorphClass())->where('auditable_id', $expense->id)
                ->where('event', 'expense_issuer_corrected')->with('user:id,name')->latest('id')->limit(50)->get()
                ->map(fn (AuditLog $log) => ['id' => $log->id, 'user' => $log->user?->name, 'created_at' => $log->created_at->toIso8601String(), 'before' => $log->old_values, 'after' => $log->new_values]) : [],
        ]);
    }

    public function documentaryReview(Request $request, ExpenseDocumentaryReview $review): Response
    {
        abort_unless(config('fiscal.grouped_expense_calculation') && $request->user()->hasRole('admin'), 403);
        $filter = $request->validate(['filter' => ['nullable', Rule::in(['all', 'with_payments', 'without_payments'])]])['filter'] ?? 'all';

        return Inertia::render('expenses/documentary-review', ['review' => $review->page($filter), 'filter' => $filter]);
    }

    public function adoptDocumentaryEvidence(Request $request, Expense $expense, ExpenseDocumentaryReview $review): RedirectResponse
    {
        abort_unless(config('fiscal.grouped_expense_calculation') && $request->user()->hasRole('admin'), 403);
        $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'documentary_evidence_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $review->adoptEvidence($expense, $request);

        return back()->with('toast', ['type' => 'success', 'message' => __('Documentary calculation evidence attached without changing financial amounts.')]);
    }

    public function store(Request $request, AccountingSynchronizer $accounting, DocumentNumber $numbers): RedirectResponse
    {
        return $this->saveExpense($request, $accounting, $numbers);
    }

    public function edit(Request $request, Expense $expense): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        abort_if($expense->status === 'paid', 403);

        return Inertia::render('expenses/edit', [
            'canUseGroupedCalculation' => config('fiscal.grouped_expense_calculation') && $request->user()->hasRole('admin'),
            'expense' => $expense->load('taxLines'),
            'revision' => ExpenseCorrection::revision($expense),
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('legal_name')->get(['id', 'legal_name']),
            'installations' => Installation::orderByDesc('id')->get(['id', 'project_reference', 'site_name']),
            'countries' => Country::orderBy('name')->get(['code', 'name']),
        ]);
    }

    public function update(Request $request, Expense $expense, AccountingSynchronizer $accounting, DocumentNumber $numbers): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $request->validate(['revision' => ['required', 'string', 'size:64'], 'correction_reason' => ['required', 'string', 'min:5', 'max:2000'], 'attachments' => ['prohibited']]);

        return $this->saveExpense($request, $accounting, $numbers, $expense);
    }

    public function previewCalculation(Request $request, ExpenseDocumentaryCalculation $calculator): JsonResponse
    {
        abort_unless(config('fiscal.grouped_expense_calculation') && $request->user()->hasRole('admin'), 403);
        $request->merge(['currency_code' => $request->input('currency_code', 'EUR'), 'tax_regime' => $request->input('tax_regime', 'standard'),
            'withholding_rate' => $request->input('withholding_rate', 0), 'withholding_amount' => $request->input('withholding_amount', 0)]);
        $data = $request->validate([
            'currency_code' => ['required', 'string', 'size:3'], 'tax_regime' => ['required', 'string', 'max:32'],
            'withholding_rate' => ['required', 'numeric'], 'withholding_amount' => ['required', 'numeric'],
            'calculation_reason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'tax_lines' => ['required', 'array', 'min:1', 'max:20'],
            'tax_lines.*.calculation_id' => ['required', 'string', 'max:64'],
            'tax_lines.*.kind' => ['required', 'string', 'max:32'],
            'tax_lines.*.taxable_base' => ['required', 'numeric'], 'tax_lines.*.tax_rate' => ['required', 'numeric'],
            'tax_lines.*.tax_amount' => ['required', 'numeric'], 'tax_lines.*.deductible_percent' => ['required', 'numeric'],
            'tax_lines.*.exemption_reason' => ['nullable', 'string', 'max:255'],
        ]);
        $calculation = $calculator->calculate($data, $data['calculation_reason'] ?? null);
        $token = Crypt::encryptString(json_encode(['user_id' => $request->user()->id, 'sha256' => $calculation['tax_calculation_snapshot']['sha256'], 'expires_at' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR));

        return response()->json(['result' => $calculation['tax_calculation_snapshot']['result'], 'preview_token' => $token]);
    }

    private function saveExpense(Request $request, AccountingSynchronizer $accounting, DocumentNumber $numbers, ?Expense $editing = null): RedirectResponse
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
            'calculation_mode' => ['nullable', Rule::in([ExpenseDocumentaryCalculation::MODE])],
            'calculation_reason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'installation_id' => ['nullable', 'exists:installations,id'],
            'type' => ['required', Rule::in(['receipt', 'simplified_invoice', 'invoice', 'other'])],
            'document_number' => [Rule::requiredIf(fn () => $request->input('type') === 'invoice'), 'nullable', 'string', 'max:255'],
            'invoice_series' => ['nullable', 'string', 'max:64'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'issuer_legal_name' => [Rule::requiredIf(fn () => $request->input('type') === 'invoice'), 'nullable', 'string', 'max:255'],
            'issuer_tax_id' => [Rule::requiredIf(fn () => $request->input('type') === 'invoice'), 'nullable', 'string', 'max:32'],
            'issuer_country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', Rule::exists('countries', 'code')],
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
            'tax_lines' => ['nullable', 'array', 'min:1', 'max:20'],
            'tax_lines.*.kind' => ['required', Rule::in(['vat', 'equivalence_surcharge'])],
            'tax_lines.*.calculation_id' => ['nullable', 'string', 'max:64'],
            'tax_lines.*.taxable_base' => ['required', 'numeric', 'min:0'],
            'tax_lines.*.tax_rate' => ['required', 'numeric', 'between:0,100'],
            'tax_lines.*.tax_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'tax_lines.*.deductible_percent' => ['required', 'numeric', 'between:0,100'],
            'tax_lines.*.exemption_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'webp'], 10240)],
        ]);

        $grouped = ($data['calculation_mode'] ?? null) === ExpenseDocumentaryCalculation::MODE || $editing?->tax_calculation_version;
        if ($grouped && (! config('fiscal.grouped_expense_calculation') || ! $request->user()->hasRole('admin')
            || ($editing?->tax_calculation_version && $editing->tax_calculation_version !== SupplierInvoiceCalculator::VERSION))) {
            throw ValidationException::withMessages(['calculation_mode' => __('Grouped expense calculation is not enabled for this operation.')]);
        }
        $calculation = $grouped ? app(ExpenseDocumentaryCalculation::class)->calculate($data, $data['calculation_reason'] ?? null) : null;
        if ($calculation) {
            try {
                $token = json_decode(Crypt::decryptString((string) $request->input('calculation_preview_token')), true, flags: JSON_THROW_ON_ERROR);
                $valid = ($token['user_id'] ?? null) === $request->user()->id && ($token['expires_at'] ?? 0) > now()->timestamp
                    && hash_equals($calculation['tax_calculation_snapshot']['sha256'], $token['sha256'] ?? '');
            } catch (Throwable) {
                $valid = false;
            }
            if (! $valid) {
                throw ValidationException::withMessages(['calculation_mode' => __('Preview the current grouped calculation before saving.')]);
            }
        }
        $taxLines = $calculation ? collect($calculation['tax_lines']) : collect($data['tax_lines'] ?? [
            ['kind' => 'vat', 'taxable_base' => $data['subtotal'], 'tax_rate' => (float) $data['subtotal'] > 0 ? (float) $data['tax_amount'] / (float) $data['subtotal'] * 100 : 0, 'tax_amount' => $data['tax_amount'], 'deductible_percent' => 100],
        ])->values()->map(function (array $line, int $index) {
            $expectedTax = round((float) $line['taxable_base'] * (float) $line['tax_rate'] / 100, 2);
            // Compare cents rather than floating-point differences at the boundary.
            if (abs((int) round($expectedTax * 100) - (int) round((float) $line['tax_amount'] * 100)) > 2) {
                throw ValidationException::withMessages(["tax_lines.{$index}.tax_amount" => __('The tax amount does not match its taxable base and rate.')]);
            }

            $deductiblePercent = $line['kind'] === 'equivalence_surcharge' ? 0 : (float) $line['deductible_percent'];

            unset($line['calculation_id']);

            return [...$line, 'position' => $index + 1, 'deductible_percent' => $deductiblePercent, 'deductible_amount' => round((float) $line['tax_amount'] * $deductiblePercent / 100, 2)];
        });

        $subtotal = $calculation['subtotal'] ?? round((float) $taxLines->where('kind', 'vat')->sum('taxable_base'), 2);
        $taxAmount = $calculation['tax_amount'] ?? round((float) $taxLines->sum('tax_amount'), 2);
        $withholdingAmount = round((float) ($data['withholding_amount'] ?? 0), 2);
        $calculatedWithholding = round($subtotal * (float) ($data['withholding_rate'] ?? 0) / 100, 2);
        if (abs($calculatedWithholding - $withholdingAmount) > 0.02) {
            throw ValidationException::withMessages(['withholding_amount' => __('The withholding amount does not match its taxable base and rate.')]);
        }

        if ($data['issuer_tax_id'] ?? null) {
            $taxIdKey = strtoupper(str_replace([' ', '-', '.'], '', trim($data['issuer_tax_id'])));
            $duplicate = Expense::whereRaw("upper(replace(replace(replace(issuer_tax_id, ' ', ''), '-', ''), '.', '')) = ?", [$taxIdKey])
                ->when($editing, fn ($query) => $query->whereKeyNot($editing->id))
                ->where(fn ($query) => $query->where('issuer_country', $data['issuer_country'] ?? 'ES')
                    ->when(($data['issuer_country'] ?? 'ES') === 'ES', fn ($query) => $query->orWhereNull('issuer_country')))
                ->where('document_number', $data['document_number'] ?? null)->exists();
            if ($duplicate && filled($data['document_number'] ?? null)) {
                throw ValidationException::withMessages(['document_number' => __('This issuer invoice is already registered.')]);
            }
        }

        $files = $data['attachments'] ?? [];
        unset($data['attachments'], $data['tax_lines'], $data['calculation_mode'], $data['calculation_reason']);
        if ($calculation) {
            $data['tax_calculation_version'] = $calculation['tax_calculation_version'];
            $data['tax_calculation_snapshot'] = $calculation['tax_calculation_snapshot'];
        }
        $data['issuer_tax_id'] = filled($data['issuer_tax_id'] ?? null) ? strtoupper(trim($data['issuer_tax_id'])) : null;
        $data['currency_code'] = strtoupper($data['currency_code']);
        $data['subtotal'] = $subtotal;
        $data['tax_amount'] = $taxAmount;
        $data['withholding_amount'] = $withholdingAmount;
        if (! $editing) {
            $data = [...$data, ...AccountingOperationInput::validate($request, 'expense')];
        }
        if ($editing) {
            if (($request->has('accounting_overrides') && $request->input('accounting_overrides') != $editing->accounting_overrides) || $request->filled('accounting_category_id')) {
                throw ValidationException::withMessages(['accounting' => __('Use the journal correction workflow for an already posted operation.')]);
            }
            app(ExpenseCorrection::class)->correct($editing, $data, $taxLines->all(), $request);

            return to_route('expenses.show', $editing)->with('toast', ['type' => 'success', 'message' => __('Expense corrected.')]);
        }
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
                if (in_array($data['type'], ['invoice', 'simplified_invoice'], true)) {
                    app(ReceivedInvoiceIdentity::class)->lockInput($data['issuer_country'] ?? 'ES', $data['issuer_tax_id'] ?? null, 'invoice', $data['document_number'] ?? '');
                }
                $data = app(ExpenseCreditorRegistry::class)->newInvoice($data, true);
                $accountingDecision = Arr::pull($data, '_accounting_control_decision');
                $number = $numbers->next('expense', $data['expense_date'])['number'];
                $amount = $data['tax_calculation_snapshot']['result']['totals']['total'] ?? round((float) $data['subtotal'] + (float) $data['tax_amount'] - (float) $data['withholding_amount'], 2);
                $expense = Expense::create([...$data, 'created_by' => $request->user()->id, 'number' => $number, 'amount' => $amount, 'status' => 'pending']);
                $expense->taxLines()->createMany($taxLines->all());
                foreach ($storedFiles as $storedFile) {
                    $expense->attachments()->create([...$storedFile, 'uploaded_by' => $request->user()->id]);
                }
                $accounting->syncExpense($expense, $request->user()->id, $accountingDecision);

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
        $paths = DB::transaction(function () use ($expense) {
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);
            abort_if($expense->payments()->withoutGlobalScope('active_supplier_payment')->exists(), 422, __('Expenses with payments cannot be deleted.'));
            abort_if($expense->accountingEntry()->exists(), 422, __('Posted expenses cannot be deleted. Reverse the accounting entry first.'));
            abort_if(DB::table('received_invoice_identities')->where('expense_id', $expense->id)->exists(), 422, 'Esta factura tiene identidad fiscal conservada. Requiere revisión autorizada; no se eliminará su evidencia.');
            $paths = $expense->attachments()->pluck('path')->all();
            $expense->delete();

            return $paths;
        });
        // Never remove evidence before the database has accepted the deletion.
        Storage::disk(config('filesystems.private'))->delete($paths);

        return to_route('expenses.index')->with('toast', ['type' => 'success', 'message' => __('Expense deleted.')]);
    }

    public function storeAttachments(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['required', 'file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'webp'], 10240)],
        ]);
        $stored = [];
        $disk = Storage::disk(config('filesystems.private'));
        try {
            foreach ($data['attachments'] as $file) {
                $path = $file->store('expenses/'.Str::uuid(), config('filesystems.private'));
                if (! $path) {
                    throw ValidationException::withMessages(['attachments' => __('The expense document could not be stored. Please try again.')]);
                }
                $stored[] = ['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id];
            }
            DB::transaction(function () use ($expense, $stored) {
                $locked = Expense::lockForUpdate()->findOrFail($expense->id);
                $locked->attachments()->createMany($stored);
            });
        } catch (Throwable $exception) {
            $disk->delete(array_column($stored, 'path'));
            throw $exception;
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Expense attachments added.')]);
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

    public function cancelPayment(Request $request, Expense $expense, SupplierPayment $payment, ExpensePaymentCancellation $cancellation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $cancellation->cancel($expense, $payment, $request->user(), $data['reason'], [
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(), 'method' => $request->method(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment cancelled. You can now register the correct payment.')]);
    }

    public function destroyPayment(SupplierPayment $payment): RedirectResponse
    {
        abort_unless(auth()->user()?->can('manage-purchase-payments'), 403);
        abort_if($payment->expense_id, 422, __('Use the administrative payment cancellation action.'));
        DB::transaction(function () use ($payment): void {
            $invoice = $payment->purchase_document_id ? PurchaseDocument::lockForUpdate()->findOrFail($payment->purchase_document_id) : null;
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->accountingEntry()->exists() || $payment->reconciliation()->exists()) {
                throw ValidationException::withMessages(['payment' => __('Posted payments cannot be deleted. Reverse the accounting entry first.')]);
            }
            $payment->delete();
            if ($invoice) {
                $this->refreshStatus(null, $invoice);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment removed.')]);
    }

    private function storePayment(Request $request, ?Expense $expense, ?PurchaseDocument $invoice, AccountingSynchronizer $accounting): void
    {
        $data = $request->validate([
            'idempotency_key' => ['nullable', 'uuid'],
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'method' => ['required', Rule::in(['bank_transfer', 'card', 'cash', 'direct_debit', 'employee_funds', 'other'])],
            'funding_source_type' => ['required', Rule::in(['bank_account', 'card', 'employee', 'ledger_account'])],
            'funding_source_id' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $data = [...$data, ...app(SupplierPaymentSourceResolver::class)->resolve($data['method'], $data['funding_source_type'], (int) $data['funding_source_id'])];
        try {
            DB::transaction(function () use ($request, $expense, $invoice, $accounting, $data) {
                $lockedExpense = $expense ? Expense::lockForUpdate()->findOrFail($expense->id) : null;
                $lockedInvoice = $invoice ? PurchaseDocument::lockForUpdate()->findOrFail($invoice->id) : null;

                $existing = ! empty($data['idempotency_key']) ? SupplierPayment::withoutGlobalScopes()->where('idempotency_key', $data['idempotency_key'])->first() : null;
                if ($existing) {
                    $same = ! $existing->voided_at && $existing->expense_id === $lockedExpense?->id && $existing->purchase_document_id === $lockedInvoice?->id
                        && $existing->paid_at->toDateString() === $data['paid_at'] && $existing->amount === BillingWorkspace::decimal($data['amount'])
                        && $existing->method === $data['method'] && $existing->funding_source_type === $data['funding_source_type'] && $existing->funding_source_id === $data['funding_source_id']
                        && ($existing->reference ?? '') === ($data['reference'] ?? '') && ($existing->notes ?? '') === ($data['notes'] ?? '');
                    if (! $same) {
                        throw ValidationException::withMessages(['amount' => __('This operation key has already been used for a different payment.')]);
                    }

                    return;
                }
                abort_if($lockedInvoice?->matching_status === 'review_required', 422, __('Approve invoice variances before registering a payment.'));

                $payable = $lockedInvoice ?? $lockedExpense;
                app(ReceivedInvoiceIdentity::class)->assertUsable($payable);
                $credits = $lockedInvoice ? (string) $lockedInvoice->creditNotes()->sum('amount') : '0';
                $outstanding = BigDecimal::of($payable->amount)->minus($payable->paid_amount)->minus(BigDecimal::of($credits)->abs());
                if (! $outstanding->isPositive() || $outstanding->isLessThan($data['amount'])) {
                    throw ValidationException::withMessages([
                        'amount' => __('The payment exceeds the outstanding document balance.'),
                    ]);
                }

                $payment = SupplierPayment::create([...$data, 'expense_id' => $lockedExpense?->id, 'purchase_document_id' => $lockedInvoice?->id, 'created_by' => $request->user()->id]);
                $accounting->syncSupplierPayment($payment, $request->user()->id);
                $this->refreshStatus($lockedExpense, $lockedInvoice);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! empty($data['idempotency_key']) && SupplierPayment::withoutGlobalScopes()->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw ValidationException::withMessages(['amount' => __('This operation key has already been used for a different payment.')]);
            }
            throw $exception;
        }
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
