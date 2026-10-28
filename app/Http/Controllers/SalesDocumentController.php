<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Installation;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\CustomerPaymentCancellation;
use App\Services\Accounting\IssuedInvoiceCorrection;
use App\Services\Billing\BillingWorkspace;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\DocumentPdf;
use App\Services\Documents\QuoteBillingPlan;
use App\Services\Documents\SalesDocumentConversion;
use App\Services\Documents\SalesDocumentCreation;
use App\Services\Finance\AcceptancePaymentManager;
use App\Services\Finance\OutstandingBalanceCalculator;
use App\Services\Inventory\SalesStockAllocator;
use App\Services\Inventory\StockLedger;
use App\Services\Operations\FinancialClearanceGuard;
use App\Services\Quotes\QuoteCalculator;
use App\Services\Security\SecurityEventRecorder;
use App\Support\HistoricalDocumentDate;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SalesDocumentController extends Controller
{
    public function create(Request $request): Response
    {
        $installation = Installation::whereKey($request->integer('installation'))->first(['id', 'person_id']);

        return Inertia::render('sales/form', [
            ...$this->formData(),
            'defaultCustomerId' => Person::where('category', 'customer')->whereKey($request->integer('customer'))->value('id') ?: $installation?->person_id,
            'defaultInstallationId' => $installation?->id,
            'defaultType' => in_array($request->string('type')->toString(), ['order', 'delivery_note', 'invoice'], true) ? $request->string('type')->toString() : 'invoice',
        ]);
    }

    public function index(Request $request): Response
    {
        if (request('view') !== 'manage' && ! in_array(request('type'), ['order', 'delivery_note'], true)) {
            return app(BillingWorkspaceController::class)->customers(request(), app(BillingWorkspace::class));
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['order', 'delivery_note', 'invoice', 'credit_note'])],
            'status' => ['nullable', Rule::in(['draft', 'issued', 'partial', 'paid', 'overdue', 'delivered'])],
        ]);
        $documents = SalesDocument::with('customer:id,first_name,last_name,company_name')
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        return Inertia::render('sales/index', [
            'documents' => $documents->latest('issue_date')->latest()->get(),
            'counts' => SalesDocument::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'filters' => $filters,
            'summary' => [
                'billed' => (float) SalesDocument::whereIn('type', ['invoice', 'credit_note'])->whereNot('status', 'draft')->sum('amount'),
                'outstanding' => (float) SalesDocument::where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->selectRaw('coalesce(sum(amount - paid_amount), 0) as total')->value('total'),
                'overdue' => (float) SalesDocument::where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->whereDate('due_date', '<', now())->selectRaw('coalesce(sum(amount - paid_amount), 0) as total')->value('total'),
            ],
        ]);
    }

    public function show(Request $request, SalesDocument $salesDocument, SalesDocumentConversion $conversion): Response
    {
        if ($request->input('view') !== 'manage' && in_array($salesDocument->type, ['invoice', 'credit_note'], true)) {
            return app(BillingWorkspaceController::class)->customerShow($request, $salesDocument, app(BillingWorkspace::class));
        }

        $isRecurring = $salesDocument->recurringServiceBilling()->exists()
            || $salesDocument->sourceDocument?->recurringServiceBilling()->exists();
        $salesDocument->setAttribute('can_issue_invoice', $request->user()->can('issue-invoices')
            && (! $isRecurring || $request->user()->can('manage-treasury')));
        $salesDocument->setAttribute('can_correct_issued', $request->user()->hasRole('admin') && $request->user()->can('manage-admin') && IssuedInvoiceCorrection::eligible($salesDocument));
        $salesDocument->setAttribute('outstanding_balance', app(OutstandingBalanceCalculator::class)->receivableDecimal($salesDocument));
        $salesDocument->setAttribute('can_record_payment', $request->user()->can('manage-treasury'));
        $salesDocument->setAttribute('can_prepare_credit_note', $request->user()->can('prepare-credit-notes'));

        return Inertia::render('sales/show', ['reconstructionHref' => $request->user()->hasRole('admin') && $request->user()->can('manage-admin') && app(DocumentPdf::class)->custody($salesDocument) === 'historical_original_missing' ? '/sales/'.$salesDocument->id.'/informative-pdf' : null, 'custodyStatus' => app(DocumentPdf::class)->custody($salesDocument), 'document' => $salesDocument->load(['customer', 'installation', 'quote:id,number', 'sourceDocument:id,number,type', 'lines.product:id,name,track_stock,track_serials', 'accountingEntry:id,source_type,source_id,number,status', 'payments.accountingEntry:id,source_type,source_id,number,status', 'deliveries:id,sales_document_id,recipient_email,status,sent_at,first_viewed_at,responded_at,revoked_at', 'recurringServiceBilling.serviceCycleCommitment.maintenancePlan:id,name']), 'corrections' => $request->user()->hasRole('admin') && $request->user()->can('manage-admin') ? AuditLog::where('auditable_type', $salesDocument->getMorphClass())->where('auditable_id', $salesDocument->id)->where('event', 'issued_invoice_corrected')->with('user:id,name')->latest()->get() : [], 'conversion' => $conversion->summary($salesDocument), 'operationKey' => (string) Str::uuid(), 'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    public function edit(SalesDocument $salesDocument): Response
    {
        abort_unless($salesDocument->status === 'draft' && ! $salesDocument->locked_at && ! $salesDocument->quote_id && ! $salesDocument->source_document_id && ! $salesDocument->recurringServiceBilling()->exists() && in_array($salesDocument->type, ['order', 'delivery_note', 'invoice'], true), 422, __('Only direct draft documents can be edited.'));

        return Inertia::render('sales/form', [
            ...$this->formData(),
            'document' => $salesDocument->load('lines'),
        ]);
    }

    public function editIssued(Request $request, SalesDocument $salesDocument): Response
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-admin') && IssuedInvoiceCorrection::eligible($salesDocument), 403);

        return Inertia::render('sales/form', [
            ...$this->formData(), 'document' => $salesDocument->load('lines'),
            'issuedCorrection' => true, 'revision' => IssuedInvoiceCorrection::revision($salesDocument),
        ]);
    }

    public function updateIssued(Request $request, SalesDocument $salesDocument, IssuedInvoiceCorrection $correction): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-admin') && IssuedInvoiceCorrection::eligible($salesDocument), 403);
        $request->validate(['revision' => ['required', 'string', 'size:64'], 'correction_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $data = $this->validateDirectDocument($request);
        abort_unless($data['type'] === 'invoice', 422, __('The document type cannot be changed after creation.'));
        $request->validate(['lines.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('sales_document_lines', 'id')->where('sales_document_id', $salesDocument->id)]]);
        foreach ($data['lines'] as $index => &$line) {
            if ($request->filled("lines.{$index}.id")) {
                $line['id'] = $request->integer("lines.{$index}.id");
            }
        }
        unset($line);
        $request->validate(['lines.*.quantity' => ['decimal:0,3'], 'lines.*.unit_price' => ['decimal:0,2'], 'lines.*.discount_percent' => ['decimal:0,2'], 'lines.*.tax_percent' => ['decimal:0,2']]);
        $correction->correct($salesDocument, $data, $request);

        return to_route('sales-documents.show', $salesDocument)->with('toast', ['type' => 'success', 'message' => __('Issued invoice updated with correction history.')]);
    }

    public function store(Request $request, QuoteCalculator $calculator): RedirectResponse
    {
        $data = $this->validateDirectDocument($request);
        $document = app(SalesDocumentCreation::class)->run($request, 'direct', $data, fn () => [$this->persistDirectDocument(new SalesDocument, $data, $request, $calculator), true])[0];

        return to_route('sales-documents.show', $document)->with('toast', ['type' => 'success', 'message' => __('Sales document created.')]);
    }

    public function update(Request $request, SalesDocument $salesDocument, QuoteCalculator $calculator): RedirectResponse
    {
        abort_unless($salesDocument->status === 'draft' && ! $salesDocument->locked_at && ! $salesDocument->quote_id && ! $salesDocument->source_document_id && ! $salesDocument->recurringServiceBilling()->exists() && in_array($salesDocument->type, ['order', 'delivery_note', 'invoice'], true), 422, __('Only direct draft documents can be edited.'));
        $data = $this->validateDirectDocument($request);
        abort_unless($data['type'] === $salesDocument->type, 422, __('The document type cannot be changed after creation.'));
        DB::transaction(function () use ($salesDocument, $data, $request, $calculator) {
            $document = SalesDocument::query()->lockForUpdate()->findOrFail($salesDocument->id);
            abort_unless($document->status === 'draft' && ! $document->locked_at && ! $document->quote_id && ! $document->source_document_id && ! $document->recurringServiceBilling()->exists() && in_array($document->type, ['order', 'delivery_note', 'invoice'], true), 422, __('Only direct draft documents can be edited.'));
            abort_unless($data['type'] === $document->type, 422, __('The document type cannot be changed after creation.'));
            $this->persistDirectDocument($document, $data, $request, $calculator);
        });

        return to_route('sales-documents.show', $salesDocument)->with('toast', ['type' => 'success', 'message' => __('Sales document updated.')]);
    }

    public function convert(Request $request, Quote $quote, QuoteBillingPlan $billing): RedirectResponse
    {
        $issueDate = HistoricalDocumentDate::fromRequest($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['order', 'delivery_note', 'invoice'])],
            'billing_percentage' => ['nullable', 'numeric', 'between:0.01,100'],
            'billing_amount' => ['nullable', 'numeric', 'min:0.01'],
            'billing_label' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless($quote->status === 'accepted', 422, __('Only accepted quotes can be converted.'));

        [$document, $created] = app(SalesDocumentCreation::class)->run($request, 'quote:'.$quote->id, [...$data, 'issue_date' => $issueDate], function () use ($request, $quote, $billing, $data, $issueDate): array {
            $quote = Quote::lockForUpdate()->with('lines')->findOrFail($quote->id);
            abort_unless($quote->status === 'accepted', 422, __('Only accepted quotes can be converted.'));
            if ($data['type'] !== 'invoice') {
                $existing = SalesDocument::where('quote_id', $quote->id)->where('type', $data['type'])->first();
                if ($existing) {
                    return [$existing, false];
                }
            }

            $billingData = null;
            if ($data['type'] === 'invoice') {
                $summary = $billing->summary($quote);
                $remaining = $summary['remaining_percentage'];
                $remainingAmount = $summary['remaining_amount'];
                if ($remainingAmount <= 0) {
                    $existing = SalesDocument::where('quote_id', $quote->id)->where('type', 'invoice')->latest('billing_sequence')->latest()->firstOrFail();

                    return [$existing, false];
                }
                $amountWasSpecified = array_key_exists('billing_amount', $data) && $data['billing_amount'] !== null;
                $requestedAmount = $amountWasSpecified
                    ? round((float) $data['billing_amount'], 2)
                    : round((float) $quote->amount * (float) ($data['billing_percentage'] ?? $remaining) / 100, 2);
                if ($requestedAmount > $remainingAmount) {
                    $field = $amountWasSpecified ? 'billing_amount' : 'billing_percentage';
                    throw ValidationException::withMessages([$field => __('Only :amount remains available to invoice.', ['amount' => number_format($remainingAmount, 2, ',', '.').' €'])]);
                }
                $isFinal = abs($requestedAmount - $remainingAmount) < 0.001;
                $percentage = $isFinal ? $remaining : round($requestedAmount / (float) $quote->amount * 100, 2);
                $installment = $billing->installmentForAmount($quote, $requestedAmount, $isFinal);
                $sequence = count($summary['documents']) + 1;
                $billingData = [
                    'billing_percentage' => $percentage,
                    'billing_sequence' => $sequence,
                    'billing_label' => $data['billing_label'] ?? ($isFinal ? __('Final invoice') : ($sequence === 1 ? __('Advance payment') : __('Progress payment :number', ['number' => $sequence]))),
                    ...$installment['totals'],
                    'lines' => $installment['lines'],
                ];
            }

            $issued = $data['type'] === 'invoice' ? null : app(DocumentNumber::class)->next($data['type'], $issueDate);
            $series = $issued['series'] ?? null;
            $number = $issued['number'] ?? 'DRAFT-INV-'.Str::upper(Str::random(8));
            $document = SalesDocument::create([
                'type' => $data['type'], 'collect_before_invoice' => $data['type'] === 'invoice', 'quote_id' => $quote->id, 'person_id' => $quote->person_id, 'installation_id' => $quote->installation_id,
                'document_series_id' => $series?->id, 'created_by' => $request->user()->id, 'number' => $number, 'status' => 'draft',
                'fulfillment_status' => $data['type'] === 'order' || $data['type'] === 'delivery_note' ? 'pending' : null, 'billing_status' => $data['type'] === 'order' || $data['type'] === 'delivery_note' ? 'pending' : null, 'issue_date' => $issueDate,
                'due_date' => $data['type'] === 'invoice'
                    ? Carbon::parse($issueDate)->addDays((int) (data_get($quote->customer_snapshot, 'payment_terms_days') ?? 30))->toDateString()
                    : null,
                'billing_percentage' => $billingData['billing_percentage'] ?? null, 'billing_sequence' => $billingData['billing_sequence'] ?? null, 'billing_label' => $billingData['billing_label'] ?? null,
                'subtotal' => $billingData['subtotal'] ?? $quote->subtotal, 'discount_amount' => $billingData['discount_amount'] ?? $quote->discount_amount,
                'tax_amount' => $billingData['tax_amount'] ?? $quote->tax_amount, 'amount' => $billingData['amount'] ?? $quote->amount, 'customer_snapshot' => $quote->customer_snapshot ?? $quote->customer?->customerSnapshot() ?? [], 'notes' => $quote->notes,
            ]);
            $document->lines()->createMany($billingData['lines'] ?? $quote->lines->where('is_selected', true)->map(fn ($line) => $line->only(['product_id', 'unit_id', 'position', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_percent', 'subtotal', 'discount_amount', 'tax_amount', 'total']))->all());

            return [$document, true];
        });

        if (! $created) {
            return to_route('sales-documents.show', $document)->with('toast', ['type' => 'info', 'message' => __('This document already exists or the quote is fully allocated.')]);
        }

        return to_route('sales-documents.show', $document)->with('toast', ['type' => 'success', 'message' => __('Document created.')]);
    }

    public function updateMilestone(Request $request, SalesDocument $salesDocument, QuoteBillingPlan $billing): RedirectResponse
    {
        $data = $request->validate(['billing_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'], 'billing_label' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($salesDocument, $billing, $data, $request) {
            $quote = Quote::lockForUpdate()->findOrFail($salesDocument->quote_id);
            $document = SalesDocument::lockForUpdate()->findOrFail($salesDocument->id);
            abort_unless($document->type === 'invoice' && $document->collect_before_invoice && $document->status === 'draft' && ! $document->locked_at, 422);
            $amount = round((float) $data['billing_amount'], 2);
            $summary = $billing->summary($quote, $document->id);
            if ($amount > $summary['remaining_amount']) {
                throw ValidationException::withMessages(['billing_amount' => __('The milestones cannot exceed the proposal total.')]);
            }
            if ($amount < round((float) $document->payments()->sum('amount'), 2)) {
                throw ValidationException::withMessages(['billing_amount' => __('The milestone amount cannot be less than its registered payments.')]);
            }
            $before = $document->only(['amount', 'billing_label']);
            $installment = $billing->installmentForAmount($quote, $amount, abs($amount - $summary['remaining_amount']) < 0.001, $document->id);
            abort_if($installment['lines'] === [], 422);
            $document->update([...$installment['totals'], 'billing_label' => $data['billing_label'], 'billing_percentage' => round($amount / (float) $quote->amount * 100, 2)]);
            $document->lines()->delete();
            $document->lines()->createMany($installment['lines']);
            if ($document->is_acceptance_invoice) {
                $quote->update(['acceptance_payment_percentage' => $document->billing_percentage]);
                app(AcceptancePaymentManager::class)->refresh($quote->fresh());
            }
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'billing_milestone_updated', 'auditable_type' => $document->getMorphClass(), 'auditable_id' => $document->id, 'old_values' => $before, 'new_values' => $document->only(['amount', 'billing_label'])]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment milestone updated.')]);
    }

    public function issue(Request $request, SalesDocument $salesDocument, AccountingSynchronizer $accounting): RedirectResponse
    {
        abort_unless(in_array($salesDocument->type, ['invoice', 'credit_note'], true) && $salesDocument->status === 'draft', 422, __('Only draft invoices can be issued.'));
        $isRecurring = $salesDocument->recurringServiceBilling()->exists()
            || $salesDocument->sourceDocument?->recurringServiceBilling()->exists();
        abort_if($isRecurring && ! $request->user()->can('manage-treasury'), 403);
        $data = $request->validate(['issue_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:issue_date']]);
        $createdArchive = null;
        $disk = Storage::disk(config('filesystems.private'));
        try {
            DB::transaction(function () use ($request, $salesDocument, $data, $accounting, $disk, &$createdArchive) {
                if ($salesDocument->quote_id) {
                    Quote::lockForUpdate()->findOrFail($salesDocument->quote_id);
                }
                $salesDocument = SalesDocument::query()->lockForUpdate()->findOrFail($salesDocument->id);
                abort_unless(in_array($salesDocument->type, ['invoice', 'credit_note'], true) && $salesDocument->status === 'draft' && $salesDocument->locked_at === null, 422, __('Only draft invoices can be issued.'));
                $isRecurring = $salesDocument->recurringServiceBilling()->exists()
                    || $salesDocument->sourceDocument?->recurringServiceBilling()->exists();
                abort_if($isRecurring && ! $request->user()->can('manage-treasury'), 403);
                if ($salesDocument->type === 'invoice' && blank($data['due_date'] ?? null)) {
                    $data['due_date'] = Carbon::parse($data['issue_date'])
                        ->addDays($salesDocument->collect_before_invoice ? 0 : (int) (data_get($salesDocument->customer_snapshot, 'payment_terms_days') ?? 30))
                        ->toDateString();
                }
                if ($salesDocument->type === 'credit_note') {
                    $original = SalesDocument::lockForUpdate()->findOrFail($salesDocument->source_document_id);
                    abort_unless($original->type === 'invoice' && $original->locked_at, 422);
                    $credited = BigDecimal::of((string) $original->childDocuments()->where('type', 'credit_note')->where('status', '<>', 'draft')->sum('amount'))->abs();
                    if ($credited->plus(BigDecimal::of($salesDocument->amount)->abs())->isGreaterThan($original->amount)) {
                        throw ValidationException::withMessages(['issue_date' => __('Credit notes cannot exceed the original invoice amount.')]);
                    }
                }
                if ($salesDocument->collect_before_invoice && round((float) $salesDocument->payments()->sum('amount'), 2) < (float) $salesDocument->amount) {
                    throw ValidationException::withMessages(['issue_date' => __('Collect the full milestone amount before issuing its invoice.')]);
                }
                $issued = app(DocumentNumber::class)->next($salesDocument->type, $data['issue_date']);
                $salesDocument->issuer_snapshot = CompanyProfile::firstOrFail()->documentSnapshot();
                $salesDocument->update([...$data, 'document_series_id' => $issued['series']->id, 'number' => $issued['number'], 'status' => 'issued', 'issued_at' => now(), 'locked_at' => now()]);
                $accounting->syncSalesDocument($salesDocument->refresh(), $request->user()->id);
                $accounting->allocateCollectedPayments($salesDocument, $request->user()->id);
                if ($salesDocument->collect_before_invoice) {
                    $this->refreshPaymentStatus($salesDocument);
                }
                $bytes = app(DocumentPdf::class)->renderForArchiving($salesDocument->refresh());
                $hash = hash('sha256', $bytes);
                $path = 'issued-invoices/'.$salesDocument->id.'/'.$hash.'.pdf';
                if (! $disk->exists($path)) {
                    $createdArchive = $path;
                    if (! $disk->put($path, $bytes)) {
                        throw new \RuntimeException('The issued invoice PDF could not be archived.');
                    }
                }
                if (! hash_equals($hash, hash('sha256', $disk->get($path)))) {
                    throw new \RuntimeException('The issued invoice archive failed its integrity check.');
                }
                $salesDocument->forceFill(['archived_pdf_path' => $path, 'archived_pdf_hash' => $hash])->save();

                if ($salesDocument->type === 'invoice') {
                    $salesDocument->recurringServiceBilling?->update(['status' => 'issued', 'issued_by' => $request->user()->id, 'issued_at' => now()]);
                } elseif ($salesDocument->type === 'credit_note') {
                    $this->refreshPaymentStatus($original);
                    $salesDocument->sourceDocument?->recurringServiceBilling?->update(['status' => 'credited']);
                }
            });

        } catch (\Throwable $exception) {
            if ($createdArchive !== null) {
                try {
                    if (! SalesDocument::whereKey($salesDocument->id)->where('archived_pdf_path', $createdArchive)->exists()) {
                        $disk->delete($createdArchive);
                    }
                } catch (\Throwable $cleanupFailure) {
                    report($cleanupFailure); // Preserve evidence when reference status cannot be established.
                }
            }
            throw $exception;
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Invoice issued and locked.')]);
    }

    public function storePayment(Request $request, SalesDocument $salesDocument, AccountingSynchronizer $accounting): RedirectResponse
    {
        abort_unless($request->user()->can('manage-treasury'), 403);
        $data = $request->validate(['idempotency_key' => ['nullable', 'uuid'], 'paid_at' => ['required', 'date'], 'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'], 'method' => ['required', Rule::in(['bank_transfer', 'card', 'cash', 'direct_debit', 'other'])], 'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string']]);
        try {
            DB::transaction(function () use ($request, $salesDocument, $data, $accounting) {
                if ($salesDocument->quote_id) {
                    Quote::lockForUpdate()->findOrFail($salesDocument->quote_id);
                }
                $document = SalesDocument::lockForUpdate()->findOrFail($salesDocument->id);
                $existing = ! empty($data['idempotency_key']) ? SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->where('idempotency_key', $data['idempotency_key'])->first() : null;
                if ($existing) {
                    if ($existing->voided_at) {
                        throw ValidationException::withMessages(['amount' => __('This operation belongs to a cancelled receipt. Reload before registering a new receipt.')]);
                    }
                    $same = $existing->sales_document_id === $document->id && $existing->paid_at->toDateString() === $data['paid_at'] && $existing->amount === BillingWorkspace::decimal($data['amount']) && $existing->method === $data['method'] && ($existing->reference ?? '') === ($data['reference'] ?? '') && ($existing->notes ?? '') === ($data['notes'] ?? '');
                    if (! $same) {
                        throw ValidationException::withMessages(['amount' => __('This operation key has already been used for a different payment.')]);
                    }

                    return;
                }
                abort_unless($document->type === 'invoice' && (($document->collect_before_invoice && $document->status === 'draft' && ! $document->locked_at) || ($document->locked_at && in_array($document->status, ['issued', 'partial', 'overdue'], true))), 422, __('Payments can only be registered against issued invoices.'));
                $credits = $document->childDocuments()->where('type', 'credit_note')->whereNotIn('status', ['draft', 'cancelled'])->sum('amount');
                $balance = BigDecimal::of($document->amount)->minus((string) $document->payments()->sum('amount'))->plus((string) $credits);
                if ($balance->isLessThan($data['amount'])) {
                    throw ValidationException::withMessages(['amount' => __('The payment exceeds the outstanding document balance.')]);
                }
                $payment = $document->payments()->create([...$data, 'amount' => BillingWorkspace::decimal($data['amount']), 'created_by' => $request->user()->id]);
                $accounting->syncCustomerPayment($payment, $request->user()->id);
                $this->refreshPaymentStatus($document);
            });

        } catch (UniqueConstraintViolationException $exception) {
            if (! empty($data['idempotency_key']) && SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw ValidationException::withMessages(['amount' => __('This operation key has already been used for a different payment.')]);
            }
            throw $exception;
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Payment registered.')]);
    }

    public function duplicate(Request $request, SalesDocument $salesDocument): RedirectResponse
    {
        abort_unless($salesDocument->type === 'invoice', 422);
        $copy = app(SalesDocumentCreation::class)->run($request, 'duplicate:'.$salesDocument->id, [], function () use ($request, $salesDocument) {
            $source = SalesDocument::with('lines')->lockForUpdate()->findOrFail($salesDocument->id);
            $copy = SalesDocument::create([...$source->only(['person_id', 'installation_id', 'subtotal', 'discount_amount', 'tax_amount', 'amount', 'customer_snapshot', 'notes']), 'type' => 'invoice', 'status' => 'draft', 'number' => 'DRAFT-INV-'.Str::upper(Str::random(12)), 'created_by' => $request->user()->id, 'issue_date' => today()->toDateString(), 'paid_amount' => '0.00']);
            $copy->lines()->createMany($source->lines->map(fn ($line) => $line->only(['product_id', 'unit_id', 'position', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_percent', 'subtotal', 'discount_amount', 'tax_amount', 'total']))->all());

            return [$copy, true];
        })[0];

        return to_route('sales-documents.show', $copy);
    }

    public function cancelPayment(Request $request, SalesDocument $salesDocument, int $payment, CustomerPaymentCancellation $cancellation): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000', 'regex:/\\S/u'],
            'operation_key' => ['required', 'uuid'],
            'revision' => ['required', 'string', 'size:64'],
            'confirmed' => ['accepted'],
        ]);
        $cancellation->cancel($salesDocument, $payment, $request->user(), trim($data['reason']), $data['operation_key'], $data['revision'], [
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            'correlation_id' => $request->attributes->get('correlation_id'), 'route' => $request->route()?->getName(), 'method' => $request->method(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Customer receipt cancelled. No bank refund has been made.')]);
    }

    public function destroyPayment(SalesDocument $salesDocument, SalesDocumentPayment $payment): RedirectResponse
    {
        abort_unless(auth()->user()?->can('manage-treasury'), 403);
        abort_unless($payment->sales_document_id === $salesDocument->id, 404);
        throw ValidationException::withMessages(['payment' => __('Use the audited cancellation action. Receipts cannot be deleted.')]);
    }

    public function creditNote(Request $request, SalesDocument $salesDocument): RedirectResponse
    {
        abort_unless($salesDocument->type === 'invoice' && $salesDocument->locked_at, 422, __('Only issued invoices can be corrected.'));
        $document = DB::transaction(function () use ($request, $salesDocument) {
            $salesDocument = SalesDocument::with('lines')->lockForUpdate()->findOrFail($salesDocument->id);
            $existing = $salesDocument->childDocuments()->where('type', 'credit_note')->oldest('id')->first();
            if ($existing) {
                return $existing;
            }
            $negative = fn ($value) => (string) BigDecimal::of((string) $value)->abs()->negated();
            $credit = SalesDocument::create([
                'type' => 'credit_note', 'source_document_id' => $salesDocument->id, 'person_id' => $salesDocument->person_id, 'installation_id' => $salesDocument->installation_id,
                'created_by' => $request->user()->id, 'number' => 'DRAFT-CN-'.Str::upper(Str::random(8)), 'status' => 'draft', 'issue_date' => now()->toDateString(),
                'billing_percentage' => $salesDocument->billing_percentage, 'billing_sequence' => $salesDocument->billing_sequence, 'billing_label' => $salesDocument->billing_label,
                'subtotal' => $negative($salesDocument->subtotal), 'discount_amount' => $negative($salesDocument->discount_amount), 'tax_amount' => $negative($salesDocument->tax_amount),
                'amount' => $negative($salesDocument->amount), 'customer_snapshot' => $salesDocument->customer_snapshot, 'notes' => __('Correction of invoice :number', ['number' => $salesDocument->number]),
            ]);
            $credit->lines()->createMany($salesDocument->lines->map(fn ($line) => [...$line->only(['product_id', 'unit_id', 'position', 'description', 'unit_price', 'discount_percent', 'tax_percent']), 'quantity' => $negative($line->quantity), 'subtotal' => $negative($line->subtotal), 'discount_amount' => $negative($line->discount_amount), 'tax_amount' => $negative($line->tax_amount), 'total' => $negative($line->total)])->all());

            return $credit;
        });

        return to_route('sales-documents.show', $document)->with('toast', ['type' => 'success', 'message' => __('Credit note draft created.')]);
    }

    public function destroy(SalesDocument $salesDocument, SalesDocumentConversion $conversion): RedirectResponse
    {
        $quote = DB::transaction(function () use ($salesDocument, $conversion) {
            $document = SalesDocument::query()->lockForUpdate()->findOrFail($salesDocument->id);
            abort_unless($document->status === 'draft' && ! $document->locked_at && ! $document->accountingEntry()->exists() && ! $document->payments()->withoutGlobalScope('active_customer_payment')->exists() && ! $document->childDocuments()->exists(), 422, __('Only unposted draft documents without derived documents can be deleted.'));
            $quote = $document->quote;
            $source = $document->sourceDocument;
            $document->delete();
            if ($source) {
                $conversion->refresh($source->fresh());
            }

            return $quote;
        });

        return $quote
            ? to_route('quotes.show', $quote)->with('toast', ['type' => 'success', 'message' => __('Draft document deleted and its billing percentage released.')])
            : to_route('sales.index')->with('toast', ['type' => 'success', 'message' => __('Draft document deleted.')]);
    }

    public function convertDocument(Request $request, SalesDocument $salesDocument, SalesDocumentConversion $conversion): RedirectResponse
    {
        $issueDate = HistoricalDocumentDate::fromRequest($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['delivery_note', 'invoice'])],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $document = app(SalesDocumentCreation::class)->run($request, 'source:'.$salesDocument->id, [...$data, 'issue_date' => $issueDate], fn () => [$conversion->convert($salesDocument, $data['type'], $data['quantities'], $request->user()->id, $issueDate), true])[0];

        return to_route('sales-documents.show', $document)->with('toast', ['type' => 'success', 'message' => __('Derived document created.')]);
    }

    public function dispatch(Request $request, SalesDocument $salesDocument, StockLedger $ledger, SalesStockAllocator $allocator, SalesDocumentConversion $conversion, FinancialClearanceGuard $financialClearance): RedirectResponse
    {
        abort_unless($salesDocument->type === 'delivery_note' && $salesDocument->status === 'draft', 422, __('Only draft delivery notes can be dispatched.'));
        if ($salesDocument->installation) {
            $financialClearance->assertAllows($salesDocument->installation, 'reserve');
        }
        $data = $request->validate(['warehouse_id' => ['nullable', 'exists:warehouses,id'], 'serials' => ['nullable', 'array'], 'serials.*' => ['nullable', 'string']]);
        $stockLines = $salesDocument->lines->filter(fn ($line) => $line->product?->track_stock)->groupBy('product_id')->map(function ($lines, $productId) use ($data) {
            return ['product_id' => (int) $productId, 'quantity' => $lines->sum(fn ($line) => abs((float) $line->quantity)), 'unit_cost' => $lines->first()->product?->cost_price, 'serials' => $data['serials'][$productId] ?? null];
        })->values()->all();
        if ($stockLines !== [] && empty($data['warehouse_id'])) {
            throw ValidationException::withMessages(['warehouse_id' => __('Select a warehouse for stock-tracked products.')]);
        }

        DB::transaction(function () use ($request, $salesDocument, $ledger, $allocator, $conversion, $data, $stockLines) {
            if ($stockLines !== []) {
                $orderId = $salesDocument->sourceDocument?->type === 'order' ? $salesDocument->source_document_id : null;
                $ledger->post(['type' => 'issue', 'warehouse_id' => $data['warehouse_id'], 'reference' => $salesDocument->number, 'sales_document_id' => $salesDocument->id, 'reservation_sales_document_id' => $orderId, 'installation_id' => $salesDocument->installation_id, 'lines' => $stockLines], $request->user()->id);
                $allocator->consumeForDelivery($salesDocument, (int) $data['warehouse_id'], $stockLines);
            }
            $salesDocument->update(['status' => 'delivered', 'fulfillment_status' => 'complete', 'locked_at' => now()]);
            $conversion->refresh($salesDocument);
        });

        return back()->with('toast', ['type' => 'success', 'message' => $stockLines === [] ? __('Delivery note marked as delivered.') : __('Delivery note dispatched and stock issued.')]);
    }

    public function pdf(SalesDocument $salesDocument, DocumentPdf $pdf): HttpResponse
    {
        $salesDocument->load('lines');

        return response($pdf->render($salesDocument, $salesDocument->type), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$salesDocument->number.'.pdf"']);
    }

    public function informativePdf(Request $request, SalesDocument $salesDocument, DocumentPdf $pdf): HttpResponse
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-admin'), 403);
        abort_unless($pdf->custody($salesDocument) === 'historical_original_missing', 409);
        $bytes = $pdf->reconstructInformative($salesDocument);
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'informative_invoice_reconstruction', 'auditable_type' => $salesDocument->getMorphClass(), 'auditable_id' => $salesDocument->id, 'new_values' => ['sha256' => hash('sha256', $bytes), 'original_replaced' => false, 'basis' => 'available_snapshots_and_lines'], 'ip_address' => $request->ip()]);

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="RECONSTRUCCION-'.$salesDocument->number.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function quotePdf(Quote $quote, DocumentPdf $pdf): HttpResponse
    {
        $quote->load('lines');

        return response($pdf->render($quote, 'quote'), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$quote->number.'.pdf"']);
    }

    public function quotePdfWithoutWatermark(Request $request, Quote $quote, DocumentPdf $pdf, SecurityEventRecorder $events): HttpResponse
    {
        $quote->load('lines');
        $events->record('quote_pdf_exported_without_watermark', $request->user(), [
            'quote_id' => $quote->id,
            'quote_number' => $quote->number,
            'quote_status' => $quote->status,
        ], 'warning');

        return response($pdf->render($quote, 'quote', true), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$quote->number.'-sin-marca.pdf"',
        ]);
    }

    private function refreshPaymentStatus(SalesDocument $document): void
    {
        app(OutstandingBalanceCalculator::class)->refreshSalesDocument($document);
    }

    private function formData(): array
    {
        return [
            'customers' => Person::where('category', 'customer')->orderBy('company_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name', 'payment_terms_days']),
            'installations' => Installation::orderBy('project_reference')->get(['id', 'person_id', 'project_reference', 'site_name']),
            'products' => Product::with(['unit:id,symbol', 'taxRate:id,rate'])->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validateDirectDocument(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['order', 'delivery_note', 'invoice'])],
            'person_id' => ['required', Rule::exists('people', 'id')->where('category', 'customer')],
            'installation_id' => ['nullable', 'exists:installations,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.unit_id' => ['nullable', 'exists:units,id'],
            'lines.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['required', 'numeric', 'between:0,100'],
            'lines.*.tax_percent' => ['required', 'numeric', 'between:0,100'],
        ]);
    }

    private function persistDirectDocument(SalesDocument $document, array $data, Request $request, QuoteCalculator $calculator): SalesDocument
    {
        $installation = isset($data['installation_id']) ? Installation::find($data['installation_id']) : null;
        abort_if($installation?->person_id && $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to the selected customer.'));
        $customer = Person::findOrFail($data['person_id']);
        if (! $document->exists && $data['type'] === 'invoice' && blank($data['due_date'] ?? null)) {
            $data['due_date'] = Carbon::parse($data['issue_date'])
                ->addDays($customer->payment_terms_days ?? 30)
                ->toDateString();
        }
        $totals = $calculator->calculate($data['lines']);
        unset($data['lines']);

        if (! $document->exists) {
            $issued = $data['type'] === 'invoice' ? null : app(DocumentNumber::class)->next($data['type'], $data['issue_date']);
            $data['number'] = $issued['number'] ?? 'DRAFT-INV-'.Str::upper(Str::random(8));
            $data['document_series_id'] = $issued['series']->id ?? null;
            $data['created_by'] = $request->user()->id;
            $data['status'] = 'draft';
            $data['fulfillment_status'] = in_array($data['type'], ['order', 'delivery_note'], true) ? 'pending' : null;
            $data['billing_status'] = in_array($data['type'], ['order', 'delivery_note'], true) ? 'pending' : null;
        }

        $document->fill([
            ...$data,
            ...array_diff_key($totals, ['lines' => true]),
            'customer_snapshot' => $customer->customerSnapshot(),
        ])->save();
        $document->lines()->delete();
        $document->lines()->createMany($totals['lines']);

        return $document;
    }
}
