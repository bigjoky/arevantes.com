<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\Installation;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Rules\SafeUpload;
use App\Services\Accounting\AccountingSynchronizer;
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
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name']),
            'installations' => Installation::orderByDesc('created_at')->get(['id', 'project_reference', 'site_name']),
            'summary' => ['pending' => $this->pendingTotal(), 'paidThisMonth' => SupplierPayment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), 'expensesThisMonth' => Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')],
        ]);
    }

    public function show(Expense $expense): Response
    {
        return Inertia::render('expenses/show', ['expense' => $expense->load(['category', 'supplier', 'attachments', 'payments'])]);
    }

    public function store(Request $request, AccountingSynchronizer $accounting, DocumentNumber $numbers): RedirectResponse
    {
        if ($request->input('supplier_id') === 'none') {
            $request->merge(['supplier_id' => null]);
        }
        if ($request->input('installation_id') === 'none') {
            $request->merge(['installation_id' => null]);
        }
        $data = $request->validate(['expense_category_id' => ['required', 'exists:expense_categories,id'], 'supplier_id' => ['nullable', 'exists:suppliers,id'], 'installation_id' => ['nullable', 'exists:installations,id'], 'type' => ['required', Rule::in(['receipt', 'invoice', 'other'])], 'document_number' => ['nullable', 'string', 'max:255'], 'merchant_name' => ['nullable', 'string', 'max:255'], 'expense_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:expense_date'], 'subtotal' => ['required', 'numeric', 'min:0'], 'tax_amount' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string'], 'attachments' => ['nullable', 'array', 'max:10'], 'attachments.*' => ['file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'webp'], 10240)]]);
        $files = $data['attachments'] ?? [];
        unset($data['attachments']);
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

            $expense = DB::transaction(function () use ($request, $data, $storedFiles, $accounting, $numbers) {
                $number = $numbers->next('expense', $data['expense_date'])['number'];
                $amount = round((float) $data['subtotal'] + (float) $data['tax_amount'], 2);
                $expense = Expense::create([...$data, 'created_by' => $request->user()->id, 'number' => $number, 'amount' => $amount, 'status' => 'pending']);
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
        $data = $request->validate(['paid_at' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'], 'method' => ['required', Rule::in(['bank_transfer', 'card', 'cash', 'direct_debit', 'other'])], 'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string']]);
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
