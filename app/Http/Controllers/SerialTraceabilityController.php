<?php

namespace App\Http\Controllers;

use App\Models\SerialNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SerialTraceabilityController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['available', 'installed', 'issued', 'returned', 'missing'])],
        ]);

        $serials = SerialNumber::query()
            ->with([
                'product:id,sku,name',
                'warehouse:id,code,name',
                'installation:id,project_reference,site_name',
                'events.fromWarehouse:id,code,name',
                'events.toWarehouse:id,code,name',
                'events.installation:id,project_reference,site_name',
                'events.movement:id,created_by,purchase_document_id,sales_document_id,work_order_id,number,type,reference,posted_at',
                'events.movement.creator:id,name',
                'events.movement.purchaseDocument:id,supplier_id,number',
                'events.movement.purchaseDocument.supplier:id,legal_name,trade_name',
                'events.movement.salesDocument:id,number',
                'events.movement.workOrder:id,number',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($product) => $product
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('inventory/serials/index', [
            'serials' => $serials,
            'counts' => SerialNumber::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => $filters,
        ]);
    }
}
