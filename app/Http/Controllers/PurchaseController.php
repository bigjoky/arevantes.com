<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReturn;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\PurchaseInvoiceMatcher;
use App\Services\Documents\PurchaseOrderConversion;
use App\Services\Documents\SupplierInvoiceExtraction;
use App\Services\Inventory\ProjectMaterialAllocator;
use App\Services\Inventory\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('purchases/index', [
            'documents' => PurchaseDocument::with(['supplier:id,legal_name,trade_name', 'salesOrder:id,number'])->latest('issue_date')->latest()->get(),
            'counts' => PurchaseDocument::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'description', 'cost_price', 'tax_rate_id', 'track_stock', 'track_serials'])->load('taxRate:id,rate'),
        ]);
    }

    public function show(PurchaseDocument $purchaseDocument, PurchaseInvoiceMatcher $matcher): Response
    {
        $orderSummary = $purchaseDocument->type === 'purchase_order' ? $matcher->summary($purchaseDocument) : null;
        $purchaseDocument->load(['supplier', 'sourceDocument:id,number,type', 'derivedDocuments:id,source_document_id,number,type,status,matching_status,amount,issue_date,supplier_number', 'salesOrder:id,number', 'approver:id,name', 'lines.product:id,name,sku,track_stock,track_serials', 'receipts.movement:id,number', 'returns.movement:id,number', 'payments', 'attachment.verifier:id,name']);

        return Inertia::render('purchases/show', [
            'document' => $purchaseDocument,
            'matchDetails' => $purchaseDocument->type === 'supplier_invoice' ? $matcher->details($purchaseDocument) : [],
            'orderSummary' => $orderSummary,
            'creditSummary' => $purchaseDocument->type === 'supplier_invoice' ? $matcher->creditable($purchaseDocument) : [],
            'creditedAmount' => $purchaseDocument->type === 'supplier_invoice' ? abs((float) $purchaseDocument->creditNotes()->sum('amount')) : 0,
            'extractionComparison' => in_array($purchaseDocument->type, ['supplier_invoice', 'supplier_credit_note'], true) ? app(SupplierInvoiceExtraction::class)->comparison($purchaseDocument) : [],
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDocument($request);
        $document = DB::transaction(function () use ($request, $data) {
            $totals = $this->calculate($data['lines'], (float) ($data['shipping_cost'] ?? 0));
            $issued = app(DocumentNumber::class)->next($data['type'], $data['issue_date']);
            $isOrder = $data['type'] === 'purchase_order';
            $document = PurchaseDocument::create(['type' => $data['type'], 'supplier_id' => $data['supplier_id'], 'document_series_id' => $issued['series']->id, 'created_by' => $request->user()->id, 'number' => $issued['number'], 'status' => $isOrder ? 'ordered' : 'draft', 'invoicing_status' => $isOrder ? 'not_invoiced' : null, 'issue_date' => $data['issue_date'], 'notes' => $data['notes'] ?? null, 'locked_at' => $isOrder ? now() : null, ...$totals['totals']]);
            $document->lines()->createMany($totals['lines']);

            return $document;
        });

        return to_route('purchases.show', $document)->with('toast', ['type' => 'success', 'message' => __('Purchase document created.')]);
    }

    public function convert(PurchaseDocument $purchaseDocument, PurchaseOrderConversion $conversion): RedirectResponse
    {
        $order = $conversion->convert($purchaseDocument, request()->user()->id);

        return to_route('purchases.show', $order)->with('toast', ['type' => 'success', 'message' => __('Purchase order created.')]);
    }

    public function updateShipping(Request $request, PurchaseDocument $purchaseDocument): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order', 422);
        $data = $request->validate(['shipping_cost' => ['required', 'numeric', 'min:0']]);

        DB::transaction(function () use ($purchaseDocument, $data): void {
            $purchaseDocument = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            $shippingCost = round((float) $data['shipping_cost'], 2);
            $purchaseDocument->update([
                'shipping_cost' => $shippingCost,
                'amount' => round((float) $purchaseDocument->subtotal + (float) $purchaseDocument->tax_amount + $shippingCost, 2),
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Transport cost updated.')]);
    }

    public function receive(Request $request, PurchaseDocument $purchaseDocument, StockLedger $ledger, ProjectMaterialAllocator $allocator): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && in_array($purchaseDocument->status, ['ordered', 'partially_received'], true), 422);
        $data = $request->validate(['warehouse_id' => ['required', 'exists:warehouses,id'], 'supplier_delivery_note' => ['nullable', 'string', 'max:255'], 'quantities' => ['required', 'array'], 'quantities.*' => ['nullable', 'numeric', 'min:0'], 'serials' => ['nullable', 'array'], 'serials.*' => ['nullable', 'string']]);
        $received = DB::transaction(function () use ($request, $purchaseDocument, $ledger, $allocator, $data) {
            $lines = $purchaseDocument->lines->map(function ($line) use ($data) {
                $quantity = (float) ($data['quantities'][$line->id] ?? 0);
                $pending = (float) $line->quantity - (float) $line->received_quantity;
                if ($quantity > $pending) {
                    abort(422, __('Received quantity exceeds the pending quantity.'));
                }
                if ($quantity <= 0 || ! $line->product->track_stock) {
                    return null;
                }

                return ['line' => $line, 'product_id' => $line->product_id, 'quantity' => $quantity, 'unit_cost' => $line->unit_price, 'serials' => $data['serials'][$line->id] ?? null];
            })->filter();
            abort_if($lines->isEmpty(), 422, __('Enter at least one stock quantity to receive.'));
            $movement = $ledger->post(['type' => 'receipt', 'warehouse_id' => $data['warehouse_id'], 'purchase_document_id' => $purchaseDocument->id, 'reference' => $purchaseDocument->number, 'lines' => $lines->map(fn ($line) => collect($line)->except('line')->all())->all()], $request->user()->id);
            foreach ($lines as $item) {
                $item['line']->increment('received_quantity', $item['quantity']);
            }
            $allocator->allocate($purchaseDocument, (int) $data['warehouse_id'], $lines->map(fn ($line) => collect($line)->only(['product_id', 'quantity'])->all()));
            $complete = $purchaseDocument->lines()->get()->every(fn ($line) => (float) $line->received_quantity >= (float) $line->quantity || ! $line->product->track_stock);
            $purchaseDocument->update(['status' => $complete ? 'received' : 'partially_received']);
            $issued = app(DocumentNumber::class)->next('goods_receipt', now());

            return GoodsReceipt::create(['purchase_document_id' => $purchaseDocument->id, 'stock_movement_id' => $movement->id, 'warehouse_id' => $data['warehouse_id'], 'created_by' => $request->user()->id, 'number' => $issued['number'], 'supplier_delivery_note' => $data['supplier_delivery_note'] ?? null, 'received_at' => now()]);
        });

        app(PurchaseInvoiceMatcher::class)->refresh($purchaseDocument->fresh());

        return back()->with('toast', ['type' => 'success', 'message' => __('Goods receipt :number posted.', ['number' => $received->number])]);
    }

    public function invoice(Request $request, PurchaseDocument $purchaseDocument, AccountingSynchronizer $accounting, PurchaseInvoiceMatcher $matcher): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && in_array($purchaseDocument->status, ['ordered', 'partially_received', 'received'], true), 422);
        $data = $request->validate([
            'supplier_number' => ['required', 'string', 'max:255', Rule::unique('purchase_documents', 'supplier_number')->where(fn ($query) => $query->where('supplier_id', $purchaseDocument->supplier_id)->where('type', 'supplier_invoice'))],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $invoice = DB::transaction(function () use ($request, $purchaseDocument, $data, $accounting, $matcher) {
            $purchaseDocument = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            $match = $matcher->match($purchaseDocument, $data['lines'] ?? []);
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

        return to_route('purchases.show', $invoice)->with('toast', ['type' => $invoice->matching_status === 'review_required' ? 'warning' : 'success', 'message' => $message]);
    }

    public function creditNote(Request $request, PurchaseDocument $purchaseDocument, AccountingSynchronizer $accounting, PurchaseInvoiceMatcher $matcher): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'supplier_invoice' && $purchaseDocument->matching_status !== 'review_required', 422);
        $data = $request->validate([
            'supplier_number' => ['required', 'string', 'max:255', Rule::unique('purchase_documents', 'supplier_number')->where(fn ($query) => $query->where('supplier_id', $purchaseDocument->supplier_id)->where('type', 'supplier_credit_note'))],
            'issue_date' => ['required', 'date'],
            'lines' => ['required', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $credit = DB::transaction(function () use ($request, $purchaseDocument, $accounting, $matcher, $data) {
            $invoice = PurchaseDocument::lockForUpdate()->with('lines')->findOrFail($purchaseDocument->id);
            $creditable = $matcher->creditable($invoice)->keyBy('line_id');
            $submitted = collect($data['lines'])->keyBy(fn (array $line) => (int) $line['line_id']);
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
            abort_if($lines->isEmpty(), 422, __('Enter at least one quantity to credit.'));

            $issued = app(DocumentNumber::class)->next('supplier_credit_note', $data['issue_date']);
            $credit = PurchaseDocument::create([
                'type' => 'supplier_credit_note', 'source_document_id' => $invoice->id, 'installation_id' => $invoice->installation_id, 'work_order_id' => $invoice->work_order_id,
                'sales_document_id' => $invoice->sales_document_id, 'supplier_id' => $invoice->supplier_id, 'document_series_id' => $issued['series']->id, 'created_by' => $request->user()->id,
                'number' => $issued['number'], 'supplier_number' => $data['supplier_number'], 'status' => 'registered', 'matching_status' => 'matched', 'issue_date' => $data['issue_date'],
                'subtotal' => round($subtotal, 2), 'tax_amount' => round($tax, 2), 'amount' => round($amount, 2), 'notes' => __('Credit of supplier invoice :number', ['number' => $invoice->number]), 'locked_at' => now(),
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

        return back()->with('toast', ['type' => 'success', 'message' => __('Goods return :number posted.', ['number' => $goodsReturn->number])]);
    }

    public function approveInvoice(Request $request, PurchaseDocument $purchaseDocument, AccountingSynchronizer $accounting): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'supplier_invoice' && $purchaseDocument->matching_status === 'review_required', 422);
        $data = $request->validate(['approval_notes' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($request, $purchaseDocument, $accounting, $data): void {
            $purchaseDocument->update([
                'status' => 'registered',
                'matching_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'approval_notes' => $data['approval_notes'],
            ]);
            $accounting->syncPurchaseDocument($purchaseDocument, $request->user()->id);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Invoice variance approved and posted to accounting.')]);
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate(['type' => ['required', Rule::in(['purchase_rfq', 'purchase_order'])], 'supplier_id' => ['required', 'exists:suppliers,id'], 'issue_date' => ['required', 'date'], 'shipping_cost' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['required', 'exists:products,id'], 'lines.*.description' => ['required', 'string'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0'], 'lines.*.discount_percent' => ['required', 'numeric', 'between:0,100'], 'lines.*.tax_percent' => ['required', 'numeric', 'between:0,100']]);
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

        return ['lines' => $result, 'totals' => ['subtotal' => $subtotal, 'tax_amount' => $tax, 'shipping_cost' => $shippingCost, 'amount' => round($amount + $shippingCost, 2)]];
    }
}
