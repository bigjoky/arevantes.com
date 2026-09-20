<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReturn;
use App\Models\Product;
use App\Models\PurchaseApprovalRule;
use App\Models\PurchaseDocument;
use App\Models\PurchaseSetting;
use App\Models\Supplier;
use App\Models\SupplierQualityIncident;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\SupplierPaymentSourceResolver;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\PurchaseInvoiceMatcher;
use App\Services\Documents\PurchaseOrderApprovalWorkflow;
use App\Services\Documents\PurchaseOrderConversion;
use App\Services\Documents\PurchaseWorkflow;
use App\Services\Documents\SupplierInvoiceExtraction;
use App\Services\Inventory\ProjectMaterialAllocator;
use App\Services\Inventory\StockLedger;
use App\Support\HistoricalDocumentDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['purchase_rfq', 'purchase_order', 'supplier_invoice', 'supplier_credit_note'])],
            'status' => ['nullable', 'string', 'max:30'],
            'attention' => ['nullable', Rule::in(['late', 'receiving', 'review', 'approval'])],
            'planning_request' => ['nullable', 'integer', Rule::exists('planning_scenario_requests', 'id')->where('area', 'purchases')],
        ]);
        if (isset($filters['planning_request'])) {
            $filters['planning_request'] = (int) $filters['planning_request'];
        }
        $lateOrders = fn ($query) => $query
            ->where('type', 'purchase_order')
            ->whereIn('status', ['ordered', 'partially_received'])
            ->where(function ($query) {
                $query->where(fn ($query) => $query
                    ->whereNotNull('supplier_confirmed_delivery_date')
                    ->whereDate('supplier_confirmed_delivery_date', '<', today()))
                    ->orWhere(fn ($query) => $query
                        ->whereNull('supplier_confirmed_delivery_date')
                        ->whereDate('expected_delivery_date', '<', today()));
            });
        $query = PurchaseDocument::query()
            ->with(['supplier:id,legal_name,trade_name', 'salesOrder:id,number'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('supplier_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($query) => $query->where('legal_name', 'like', "%{$search}%")->orWhere('trade_name', 'like', "%{$search}%"))))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['planning_request'] ?? null, fn ($query, $planningRequest) => $query->where('planning_scenario_request_id', $planningRequest))
            ->when(($filters['attention'] ?? null) === 'late', $lateOrders)
            ->when(($filters['attention'] ?? null) === 'receiving', fn ($query) => $query->where('type', 'purchase_order')->whereIn('status', ['ordered', 'partially_received']))
            ->when(($filters['attention'] ?? null) === 'review', fn ($query) => $query->where('matching_status', 'review_required'))
            ->when(($filters['attention'] ?? null) === 'approval', fn ($query) => $query->where('type', 'purchase_order')->where('status', 'pending_approval'));

        return Inertia::render('purchases/index', [
            'documents' => $query->latest('issue_date')->latest()->paginate(25)->withQueryString(),
            'counts' => PurchaseDocument::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'attention' => [
                'late' => $lateOrders(PurchaseDocument::query())->count(),
                'receiving' => PurchaseDocument::where('type', 'purchase_order')->whereIn('status', ['ordered', 'partially_received'])->count(),
                'review' => PurchaseDocument::where('matching_status', 'review_required')->count(),
                'approval' => PurchaseDocument::where('type', 'purchase_order')->where('status', 'pending_approval')->count(),
            ],
            'filters' => $filters,
            'settings' => PurchaseSetting::current(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'description', 'cost_price', 'tax_rate_id', 'track_stock', 'track_serials'])->load('taxRate:id,rate'),
            'approvalRules' => PurchaseApprovalRule::with('supplier:id,legal_name,trade_name')->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function show(PurchaseDocument $purchaseDocument, PurchaseInvoiceMatcher $matcher, SupplierPaymentSourceResolver $paymentSources): Response
    {
        $orderSummary = $purchaseDocument->type === 'purchase_order' ? $matcher->summary($purchaseDocument) : null;
        $purchaseDocument->load(['supplier', 'assignee:id,name', 'destinationWarehouse:id,code,name', 'sourceDocument:id,number,type,shipping_cost', 'derivedDocuments:id,source_document_id,number,type,status,matching_status,amount,issue_date,supplier_number', 'salesOrder:id,number', 'approver:id,name', 'orderApprovals.rule:id,name', 'orderApprovals.requester:id,name', 'orderApprovals.decider:id,name', 'lines.product:id,name,sku,track_stock,track_serials', 'receipts.movement:id,number', 'returns.movement:id,number', 'payments.ledgerAccount', 'payments.fundingBankAccount', 'payments.fundingCard', 'payments.fundingEmployee', 'attachment.verifier:id,name', 'events.user:id,name', 'qualityIncidents.product:id,sku,name', 'qualityIncidents.warehouse:id,code,name', 'qualityIncidents.reporter:id,name', 'qualityIncidents.resolver:id,name']);
        $purchaseDocument->payments->each(fn ($payment) => $payment->setAttribute('source_label', $payment->sourceLabel()));

        return Inertia::render('purchases/show', [
            'document' => $purchaseDocument,
            'supplierIdentity' => $purchaseDocument->supplierIdentity(),
            'matchDetails' => $purchaseDocument->type === 'supplier_invoice' ? $matcher->details($purchaseDocument) : [],
            'orderSummary' => $orderSummary,
            'creditSummary' => $purchaseDocument->type === 'supplier_invoice' ? $matcher->creditable($purchaseDocument) : [],
            'creditableShipping' => $purchaseDocument->type === 'supplier_invoice' ? $matcher->creditableShipping($purchaseDocument) : 0,
            'creditedAmount' => $purchaseDocument->type === 'supplier_invoice' ? abs((float) $purchaseDocument->creditNotes()->sum('amount')) : 0,
            'extractionComparison' => in_array($purchaseDocument->type, ['supplier_invoice', 'supplier_credit_note'], true) ? app(SupplierInvoiceExtraction::class)->comparison($purchaseDocument) : [],
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name']),
            'paymentSources' => $paymentSources->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDocument($request);
        $document = DB::transaction(function () use ($request, $data) {
            $totals = $this->calculate($data['lines'], (float) ($data['shipping_cost'] ?? 0));
            $issued = app(DocumentNumber::class)->next($data['type'], $data['issue_date']);
            $isOrder = $data['type'] === 'purchase_order';
            $document = PurchaseDocument::create(['type' => $data['type'], 'supplier_id' => $data['supplier_id'], 'document_series_id' => $issued['series']->id, 'created_by' => $request->user()->id, 'assigned_to' => $isOrder ? $request->user()->id : null, 'assigned_by' => $isOrder ? $request->user()->id : null, 'assigned_at' => $isOrder ? now() : null, 'number' => $issued['number'], 'status' => $isOrder ? 'ordered' : 'draft', 'invoicing_status' => $isOrder ? 'not_invoiced' : null, 'issue_date' => $data['issue_date'], 'expected_delivery_date' => $data['expected_delivery_date'] ?? null, 'notes' => $data['notes'] ?? null, 'locked_at' => $isOrder ? now() : null, ...$totals['totals']]);
            $document->lines()->createMany($totals['lines']);
            $requiresApproval = $isOrder && app(PurchaseOrderApprovalWorkflow::class)->request($document, $request->user()->id);
            if (! $requiresApproval) {
                app(PurchaseWorkflow::class)->record($document, 'created', $request->user()->id, null, $document->status);
            }

            return $document;
        });

        return to_route('purchases.show', $document)->with('toast', ['type' => 'success', 'message' => __('Purchase document created.')]);
    }

    public function convert(PurchaseDocument $purchaseDocument, PurchaseOrderConversion $conversion): RedirectResponse
    {
        $date = HistoricalDocumentDate::fromRequest(request());
        $order = $conversion->convert($purchaseDocument, request()->user()->id, issueDate: $date);

        return to_route('purchases.show', $order)->with('toast', ['type' => 'success', 'message' => __('Purchase order created.')]);
    }

    public function receive(Request $request, PurchaseDocument $purchaseDocument, StockLedger $ledger, ProjectMaterialAllocator $allocator): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && in_array($purchaseDocument->status, ['ordered', 'partially_received'], true), 422);
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'supplier_delivery_note' => ['nullable', 'string', 'max:255'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
            'serials' => ['nullable', 'array'],
            'serials.*' => ['nullable', 'string'],
            'rejected_quantities' => ['nullable', 'array'],
            'rejected_quantities.*' => ['nullable', 'numeric', 'min:0'],
            'rejection_reasons' => ['nullable', 'array'],
            'rejection_reasons.*' => ['nullable', Rule::in(['damaged', 'wrong_item', 'defective', 'incomplete', 'documentation', 'other'])],
            'rejection_severities' => ['nullable', 'array'],
            'rejection_severities.*' => ['nullable', Rule::in(['minor', 'major', 'critical'])],
            'rejection_notes' => ['nullable', 'array'],
            'rejection_notes.*' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = DB::transaction(function () use ($request, $purchaseDocument, $ledger, $allocator, $data) {
            $order = PurchaseDocument::lockForUpdate()->with('lines.product')->findOrFail($purchaseDocument->id);
            $acceptedLines = collect();
            $rejectedLines = collect();

            foreach ($order->lines as $line) {
                $quantity = round((float) ($data['quantities'][$line->id] ?? 0), 3);
                $rejectedQuantity = round((float) ($data['rejected_quantities'][$line->id] ?? 0), 3);
                $pending = (float) $line->quantity - (float) $line->received_quantity;
                abort_if($quantity + $rejectedQuantity > $pending + 0.0005, 422, __('Accepted and rejected quantities exceed the pending quantity.'));
                abort_if(($quantity > 0 || $rejectedQuantity > 0) && ! $line->product->track_stock, 422, __('Only stock-tracked products can be received.'));

                if ($quantity > 0) {
                    $acceptedLines->push(['line' => $line, 'product_id' => $line->product_id, 'quantity' => $quantity, 'unit_cost' => $line->unit_price, 'serials' => $data['serials'][$line->id] ?? null]);
                }

                if ($rejectedQuantity > 0) {
                    $reason = $data['rejection_reasons'][$line->id] ?? null;
                    abort_if(! $reason, 422, __('Select a rejection reason for every rejected quantity.'));
                    $rejectedLines->push([
                        'line' => $line,
                        'quantity' => $rejectedQuantity,
                        'reason' => $reason,
                        'severity' => $data['rejection_severities'][$line->id] ?? 'minor',
                        'notes' => $data['rejection_notes'][$line->id] ?? null,
                    ]);
                }
            }

            abort_if($acceptedLines->isEmpty() && $rejectedLines->isEmpty(), 422, __('Enter at least one accepted or rejected quantity.'));

            $receipt = null;
            if ($acceptedLines->isNotEmpty()) {
                $movement = $ledger->post(['type' => 'receipt', 'warehouse_id' => $data['warehouse_id'], 'purchase_document_id' => $order->id, 'reference' => $order->number, 'lines' => $acceptedLines->map(fn ($line) => collect($line)->except('line')->all())->all()], $request->user()->id);
                foreach ($acceptedLines as $item) {
                    $item['line']->increment('received_quantity', $item['quantity']);
                }
                $allocator->allocate($order, (int) $data['warehouse_id'], $acceptedLines->map(fn ($line) => collect($line)->only(['product_id', 'quantity'])->all()));
                $complete = $order->lines()->with('product')->get()->every(fn ($line) => (float) $line->received_quantity >= (float) $line->quantity || ! $line->product->track_stock);
                $order->update(['status' => $complete ? 'received' : 'partially_received']);
                $issued = app(DocumentNumber::class)->next('goods_receipt', now());
                $receipt = GoodsReceipt::create(['purchase_document_id' => $order->id, 'stock_movement_id' => $movement->id, 'warehouse_id' => $data['warehouse_id'], 'created_by' => $request->user()->id, 'number' => $issued['number'], 'supplier_delivery_note' => $data['supplier_delivery_note'] ?? null, 'received_at' => now()]);
            }

            foreach ($rejectedLines as $item) {
                SupplierQualityIncident::create([
                    'purchase_document_id' => $order->id,
                    'goods_receipt_id' => $receipt?->id,
                    'product_id' => $item['line']->product_id,
                    'warehouse_id' => $data['warehouse_id'],
                    'reported_by' => $request->user()->id,
                    'rejected_quantity' => $item['quantity'],
                    'reason' => $item['reason'],
                    'severity' => $item['severity'],
                    'notes' => $item['notes'],
                    'status' => 'open',
                ]);
            }

            return ['receipt' => $receipt, 'incident_count' => $rejectedLines->count(), 'status' => $order->fresh()->status];
        });

        app(PurchaseInvoiceMatcher::class)->refresh($purchaseDocument->fresh());
        if ($result['receipt']) {
            app(PurchaseWorkflow::class)->record($purchaseDocument, 'goods_received', $request->user()->id, $purchaseDocument->status, $result['status'], ['receipt' => $result['receipt']->number]);
        }
        if ($result['incident_count'] > 0) {
            app(PurchaseWorkflow::class)->record($purchaseDocument, 'quality_incident_reported', $request->user()->id, $result['status'], $result['status'], ['incidents' => $result['incident_count']]);
        }

        $message = match (true) {
            $result['receipt'] && $result['incident_count'] > 0 => __('Goods receipt :number posted with :count rejected line(s).', ['number' => $result['receipt']->number, 'count' => $result['incident_count']]),
            (bool) $result['receipt'] => __('Goods receipt :number posted.', ['number' => $result['receipt']->number]),
            default => __(':count supplier quality incident(s) reported.', ['count' => $result['incident_count']]),
        };

        return back()->with('toast', ['type' => $result['incident_count'] > 0 ? 'warning' : 'success', 'message' => $message]);
    }

    public function invoice(Request $request, PurchaseDocument $purchaseDocument, AccountingSynchronizer $accounting, PurchaseInvoiceMatcher $matcher): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && in_array($purchaseDocument->status, ['ordered', 'partially_received', 'received'], true), 422);
        $data = $request->validate([
            'supplier_number' => ['required', 'string', 'max:255', Rule::unique('purchase_documents', 'supplier_number')->where(fn ($query) => $query->where('supplier_id', $purchaseDocument->supplier_id)->where('type', 'supplier_invoice'))],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $invoice = DB::transaction(function () use ($request, $purchaseDocument, $data, $accounting, $matcher) {
            $purchaseDocument = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            $match = $matcher->match($purchaseDocument, $data['lines'] ?? [], isset($data['shipping_cost']) ? (float) $data['shipping_cost'] : null);
            $issued = app(DocumentNumber::class)->next('supplier_invoice', $data['issue_date']);
            $reviewRequired = $match['matching_status'] === 'review_required';
            $invoice = PurchaseDocument::create(['type' => 'supplier_invoice', 'source_document_id' => $purchaseDocument->id, 'installation_id' => $purchaseDocument->installation_id, 'work_order_id' => $purchaseDocument->work_order_id, 'sales_document_id' => $purchaseDocument->sales_document_id, 'supplier_id' => $purchaseDocument->supplier_id, 'document_series_id' => $issued['series']->id, 'created_by' => $request->user()->id, 'number' => $issued['number'], 'supplier_number' => $data['supplier_number'], 'status' => $reviewRequired ? 'review_required' : 'registered', 'matching_status' => $match['matching_status'], 'match_variance_amount' => $match['variance_amount'], 'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'] ?? null, ...$match['totals'], 'notes' => $purchaseDocument->notes, 'locked_at' => now()]);
            $invoice->lines()->createMany($match['lines']);
            if (! $reviewRequired) {
                $accounting->syncPurchaseDocument($invoice, $request->user()->id);
            }
            $matcher->refresh($purchaseDocument);

            return $invoice;
        });

        $message = $invoice->matching_status === 'review_required'
            ? __('Supplier invoice registered with variances. Approval is required.')
            : __('Supplier invoice registered and matched.');
        app(PurchaseWorkflow::class)->record($purchaseDocument, 'invoice_registered', $request->user()->id, $purchaseDocument->status, $purchaseDocument->fresh()->status, ['invoice' => $invoice->number, 'matching_status' => $invoice->matching_status]);

        return to_route('purchases.show', $invoice)->with('toast', ['type' => $invoice->matching_status === 'review_required' ? 'warning' : 'success', 'message' => $message]);
    }

    public function creditNote(Request $request, PurchaseDocument $purchaseDocument, AccountingSynchronizer $accounting, PurchaseInvoiceMatcher $matcher): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'supplier_invoice' && $purchaseDocument->matching_status !== 'review_required', 422);
        $data = $request->validate([
            'supplier_number' => ['required', 'string', 'max:255', Rule::unique('purchase_documents', 'supplier_number')->where(fn ($query) => $query->where('supplier_id', $purchaseDocument->supplier_id)->where('type', 'supplier_credit_note'))],
            'issue_date' => ['required', 'date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $credit = DB::transaction(function () use ($request, $purchaseDocument, $accounting, $matcher, $data) {
            $invoice = PurchaseDocument::lockForUpdate()->with('lines')->findOrFail($purchaseDocument->id);
            $creditable = $matcher->creditable($invoice)->keyBy('line_id');
            $submitted = collect($data['lines'] ?? [])->keyBy(fn (array $line) => (int) $line['line_id']);
            abort_if($submitted->keys()->diff($creditable->keys())->isNotEmpty(), 422, __('The credit note contains lines outside this invoice.'));

            $subtotal = $tax = $amount = 0;
            $lines = $invoice->lines->map(function ($line) use ($submitted, $creditable, &$subtotal, &$tax, &$amount) {
                $quantity = round((float) ($submitted->get($line->id)['quantity'] ?? 0), 3);
                abort_if($quantity > (float) $creditable[$line->id]['creditable_quantity'] + 0.0005, 422, __('Credited quantity exceeds the available invoice quantity.'));
                if ($quantity <= 0) {
                    return null;
                }
                $base = -round($quantity * (float) $line->unit_price * (1 - (float) $line->discount_percent / 100), 2);
                $lineTax = -round(abs($base) * (float) $line->tax_percent / 100, 2);
                $subtotal += $base;
                $tax += $lineTax;
                $amount += $base + $lineTax;

                return [...$line->only(['product_id', 'position', 'description', 'unit_price', 'discount_percent', 'tax_percent']), 'source_line_id' => $line->id, 'quantity' => -$quantity, 'received_quantity' => 0, 'returned_quantity' => 0, 'subtotal' => $base, 'tax_amount' => $lineTax, 'total' => $base + $lineTax];
            })->filter()->values();
            $shippingCost = round((float) ($data['shipping_cost'] ?? 0), 2);
            $creditableShipping = $matcher->creditableShipping($invoice);
            abort_if($shippingCost > $creditableShipping + 0.005, 422, __('Credited transport exceeds the available invoice transport.'));
            abort_if($lines->isEmpty() && $shippingCost <= 0, 422, __('Enter at least one quantity or transport amount to credit.'));
            $shippingTaxRate = (float) PurchaseSetting::current()->shipping_tax_percent;
            $shippingTax = round($shippingCost * $shippingTaxRate / 100 + 1e-9, 2);
            $subtotal -= $shippingCost;
            $tax -= $shippingTax;
            $amount -= $shippingCost + $shippingTax;

            $issued = app(DocumentNumber::class)->next('supplier_credit_note', $data['issue_date']);
            $credit = PurchaseDocument::create([
                'type' => 'supplier_credit_note', 'source_document_id' => $invoice->id, 'installation_id' => $invoice->installation_id, 'work_order_id' => $invoice->work_order_id,
                'sales_document_id' => $invoice->sales_document_id, 'supplier_id' => $invoice->supplier_id, 'document_series_id' => $issued['series']->id, 'created_by' => $request->user()->id,
                'number' => $issued['number'], 'supplier_number' => $data['supplier_number'], 'status' => 'registered', 'matching_status' => 'matched', 'issue_date' => $data['issue_date'],
                'subtotal' => round($subtotal, 2), 'tax_amount' => round($tax, 2), 'shipping_cost' => -$shippingCost, 'amount' => round($amount, 2), 'notes' => __('Credit of supplier invoice :number', ['number' => $invoice->number]), 'locked_at' => now(),
            ]);
            $credit->lines()->createMany($lines->all());
            $accounting->syncPurchaseDocument($credit, $request->user()->id);
            if ($invoice->sourceDocument) {
                $matcher->refresh($invoice->sourceDocument);
            }

            return $credit;
        });

        return to_route('purchases.show', $credit)->with('toast', ['type' => 'success', 'message' => __('Supplier credit note registered and posted.')]);
    }

    public function returnGoods(Request $request, PurchaseDocument $purchaseDocument, StockLedger $ledger, PurchaseInvoiceMatcher $matcher): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order', 422);
        $data = $request->validate(['warehouse_id' => ['required', 'exists:warehouses,id'], 'supplier_return_number' => ['nullable', 'string', 'max:255'], 'quantities' => ['required', 'array'], 'quantities.*' => ['nullable', 'numeric', 'min:0'], 'serials' => ['nullable', 'array'], 'serials.*' => ['nullable', 'string']]);

        $goodsReturn = DB::transaction(function () use ($request, $purchaseDocument, $ledger, $matcher, $data) {
            $order = PurchaseDocument::lockForUpdate()->with('lines.product')->findOrFail($purchaseDocument->id);
            $lines = $order->lines->map(function ($line) use ($data) {
                $quantity = round((float) ($data['quantities'][$line->id] ?? 0), 3);
                $returnable = (float) $line->received_quantity - (float) $line->returned_quantity;
                abort_if($quantity > $returnable + 0.0005, 422, __('Returned quantity exceeds received stock.'));
                if ($quantity <= 0 || ! $line->product->track_stock) {
                    return null;
                }

                return ['line' => $line, 'product_id' => $line->product_id, 'quantity' => -$quantity, 'unit_cost' => $line->unit_price, 'serials' => $data['serials'][$line->id] ?? null];
            })->filter();
            abort_if($lines->isEmpty(), 422, __('Enter at least one stock quantity to return.'));
            $movement = $ledger->post(['type' => 'purchase_return', 'warehouse_id' => $data['warehouse_id'], 'purchase_document_id' => $order->id, 'reference' => $order->number, 'lines' => $lines->map(fn ($line) => collect($line)->except('line')->all())->all()], $request->user()->id);
            foreach ($lines as $item) {
                $item['line']->increment('returned_quantity', abs((float) $item['quantity']));
            }
            $issued = app(DocumentNumber::class)->next('goods_return', now());
            $result = GoodsReturn::create(['purchase_document_id' => $order->id, 'stock_movement_id' => $movement->id, 'warehouse_id' => $data['warehouse_id'], 'created_by' => $request->user()->id, 'number' => $issued['number'], 'supplier_return_number' => $data['supplier_return_number'] ?? null, 'returned_at' => now()]);
            $matcher->refresh($order);

            return $result;
        });
        app(PurchaseWorkflow::class)->record($purchaseDocument, 'goods_returned', $request->user()->id, $purchaseDocument->status, $purchaseDocument->status, ['return' => $goodsReturn->number]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Goods return :number posted.', ['number' => $goodsReturn->number])]);
    }

    public function approveInvoice(Request $request, PurchaseDocument $purchaseDocument, AccountingSynchronizer $accounting): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'supplier_invoice' && $purchaseDocument->matching_status === 'review_required', 422);
        $data = $request->validate(['approval_notes' => ['required', 'string', 'max:2000']]);
        abort_if($purchaseDocument->created_by === $request->user()->id && ! $request->user()->hasRole('admin'), 403, __('A different authorised user must approve this invoice.'));

        DB::transaction(function () use ($request, $purchaseDocument, $accounting, $data): void {
            $purchaseDocument = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            abort_unless($purchaseDocument->matching_status === 'review_required', 422);
            $purchaseDocument->update([
                'status' => 'registered',
                'matching_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'approval_notes' => $data['approval_notes'],
            ]);
            $accounting->syncPurchaseDocument($purchaseDocument, $request->user()->id);
            app(PurchaseWorkflow::class)->record($purchaseDocument, 'variance_approved', $request->user()->id, 'review_required', 'approved', ['notes' => $data['approval_notes']]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Invoice variance approved and posted to accounting.')]);
    }

    public function approveOrder(Request $request, PurchaseDocument $purchaseDocument, PurchaseOrderApprovalWorkflow $approvals): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && $purchaseDocument->status === 'pending_approval', 422);
        $data = $request->validate([
            'decision' => ['nullable', Rule::in(['approved', 'rejected'])],
            'approval_notes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(fn () => $approvals->decide($purchaseDocument, $request->user(), $data['decision'] ?? 'approved', $data['approval_notes']));

        return back()->with('toast', ['type' => 'success', 'message' => ($data['decision'] ?? 'approved') === 'rejected' ? __('Purchase order rejected.') : __('Purchase approval recorded.')]);
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate(['type' => ['required', Rule::in(['purchase_rfq', 'purchase_order'])], 'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)], 'issue_date' => ['required', 'date'], 'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:issue_date'], 'shipping_cost' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)], 'lines.*.description' => ['required', 'string'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0'], 'lines.*.discount_percent' => ['required', 'numeric', 'between:0,100'], 'lines.*.tax_percent' => ['required', 'numeric', 'between:0,100']]);
    }

    private function calculate(array $lines, float $shippingCost = 0): array
    {
        $subtotal = $tax = $amount = 0;
        $result = [];
        foreach ($lines as $index => $line) {
            $base = round($line['quantity'] * $line['unit_price'] * (1 - $line['discount_percent'] / 100), 2);
            $lineTax = round($base * $line['tax_percent'] / 100, 2);
            $result[] = [...$line, 'position' => $index + 1, 'subtotal' => $base, 'tax_amount' => $lineTax, 'total' => $base + $lineTax];
            $subtotal += $base;
            $tax += $lineTax;
            $amount += $base + $lineTax;
        }

        $shippingTax = round($shippingCost * (float) PurchaseSetting::current()->shipping_tax_percent / 100 + 1e-9, 2);

        return ['lines' => $result, 'totals' => ['subtotal' => round($subtotal + $shippingCost, 2), 'tax_amount' => round($tax + $shippingTax, 2), 'shipping_cost' => $shippingCost, 'amount' => round($amount + $shippingCost + $shippingTax, 2)]];
    }
}
