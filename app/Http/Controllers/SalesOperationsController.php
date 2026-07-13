<?php

namespace App\Http\Controllers;

use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Documents\SalesDocumentConversion;
use App\Services\Inventory\SalesProcurement;
use App\Services\Inventory\SalesStockAllocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesOperationsController extends Controller
{
    public function index(SalesDocumentConversion $conversion, SalesStockAllocator $stock): Response
    {
        $orders = SalesDocument::query()
            ->where('type', 'order')
            ->where(fn ($query) => $query->whereNot('fulfillment_status', 'complete')->orWhereNot('billing_status', 'complete'))
            ->with(['customer:id,first_name,last_name,company_name', 'installation:id,project_reference,site_name', 'lines.product:id,sku,name,track_stock', 'purchaseDocuments.supplier:id,legal_name,trade_name'])
            ->oldest('issue_date')
            ->get()
            ->map(function (SalesDocument $order) use ($conversion, $stock): array {
                $progress = $conversion->summary($order);

                return [
                    ...$this->documentData($order),
                    'fulfillment_status' => $order->fulfillment_status,
                    'billing_status' => $order->billing_status,
                    'progress' => $progress['lines'],
                    'stock' => $stock->status($order, $progress),
                    'purchases' => $order->purchaseDocuments->sortByDesc('id')->map(fn ($purchase) => [
                        'id' => $purchase->id,
                        'number' => $purchase->number,
                        'type' => $purchase->type,
                        'status' => $purchase->status,
                        'procurement_batch_id' => $purchase->procurement_batch_id,
                        'supplier' => $purchase->supplier?->trade_name ?: $purchase->supplier?->legal_name,
                    ])->values(),
                ];
            });

        $deliveryNotes = SalesDocument::query()
            ->where('type', 'delivery_note')
            ->whereIn('status', ['draft', 'delivered'])
            ->where(fn ($query) => $query->where('status', 'draft')->orWhereNot('billing_status', 'complete'))
            ->with(['customer:id,first_name,last_name,company_name', 'installation:id,project_reference,site_name', 'sourceDocument:id,number,type', 'lines.product:id,sku,name,track_stock,track_serials'])
            ->oldest('issue_date')
            ->get();

        $dispatches = $deliveryNotes->where('status', 'draft')->map(fn (SalesDocument $document) => [
            ...$this->documentData($document),
            'has_stock' => $document->lines->contains(fn ($line) => $line->product?->track_stock),
            'has_serials' => $document->lines->contains(fn ($line) => $line->product?->track_stock && $line->product?->track_serials),
        ])->values();

        $billing = $deliveryNotes->where('status', 'delivered')->map(function (SalesDocument $document) use ($conversion): array {
            $progress = $conversion->summary($document);

            return [
                ...$this->documentData($document),
                'progress' => $progress['lines'],
            ];
        })->filter(fn ($document) => collect($document['progress'])->sum('remaining_invoice_quantity') > 0)->values();

        return Inertia::render('sales/workflow', [
            'orders' => $orders,
            'dispatches' => $dispatches,
            'billing' => $billing,
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name']),
            'summary' => [
                'orders' => $orders->count(),
                'dispatches' => $dispatches->count(),
                'billing' => $billing->count(),
                'shortages' => $orders->where('stock.shortage', '>', 0)->count(),
            ],
        ]);
    }

    public function reserve(Request $request, SalesDocument $salesDocument, SalesStockAllocator $allocator): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'reserved_until' => ['nullable', 'date', 'after:now'],
        ]);
        $created = $allocator->reserve($salesDocument, Warehouse::findOrFail($data['warehouse_id']), $data['reserved_until'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => trans_choice(':count stock reservation created.|:count stock reservations created.', $created, ['count' => $created])]);
    }

    public function procure(Request $request, SalesDocument $salesDocument, SalesProcurement $procurement): RedirectResponse
    {
        $data = $request->validate([
            'supplier_ids' => ['required', 'array', 'min:1', 'max:10'],
            'supplier_ids.*' => ['required', 'integer', 'distinct', 'exists:suppliers,id'],
        ]);
        $suppliers = Supplier::where('is_active', true)->whereIn('id', $data['supplier_ids'])->get();
        if ($suppliers->count() !== count($data['supplier_ids'])) {
            throw ValidationException::withMessages(['supplier_ids' => __('Select active suppliers only.')]);
        }
        $purchases = $procurement->createRequests($salesDocument, $suppliers, $request->user()->id);

        return to_route('purchase-comparisons.show', $purchases->first()->procurement_batch_id)->with('toast', ['type' => 'success', 'message' => trans_choice(':count supplier request created.|:count supplier requests created.', $purchases->count(), ['count' => $purchases->count()])]);
    }

    private function documentData(SalesDocument $document): array
    {
        $snapshot = $document->customer_snapshot ?? [];
        $customer = $document->customer?->company_name
            ?: trim(($document->customer?->first_name ?? '').' '.($document->customer?->last_name ?? ''));
        $customer = $customer ?: ($snapshot['company_name'] ?? trim(($snapshot['first_name'] ?? '').' '.($snapshot['last_name'] ?? '')));

        return [
            'id' => $document->id,
            'number' => $document->number,
            'status' => $document->status,
            'issue_date' => $document->issue_date?->toDateString(),
            'amount' => $document->amount,
            'customer' => $customer,
            'installation' => $document->installation ? [
                'id' => $document->installation->id,
                'reference' => $document->installation->project_reference,
                'name' => $document->installation->site_name,
            ] : null,
            'source_document' => $document->sourceDocument ? ['id' => $document->sourceDocument->id, 'number' => $document->sourceDocument->number] : null,
        ];
    }
}
