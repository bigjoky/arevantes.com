<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\MaterialTransport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MaterialTransportController extends Controller
{
    public function create()
    {
        return Inertia::render('supply/transport', [
            'products' => Product::where('track_stock', true)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'track_serials']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_usable']),
            'installations' => Installation::orderBy('project_reference')->get(['id', 'project_reference', 'site_name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'deliveries' => StockMovement::where('type', 'delivery')->latest()->limit(100)->get(['id', 'number', 'installation_id']),
        ]);
    }

    public function store(Request $request, MaterialTransport $transport)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['delivery', 'internal_return'])],
            'operation_key' => ['required', 'uuid'],
            'installation_id' => ['required', 'integer', 'exists:installations,id'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'source_movement_id' => ['nullable', 'required_if:type,internal_return', 'integer', 'exists:stock_movements,id'],
            'source_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('track_stock', true)->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'serials' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'material_condition' => ['required', Rule::in(['usable', 'damaged'])],
        ]);
        abort_if($data['type'] === 'delivery' && $data['material_condition'] !== 'usable', 422);
        $transport->post($data, $request->user()->id);

        return to_route('inventory.index', ['view' => $data['type'] === 'delivery' ? 'deliveries' : 'returns'])->with('toast', ['type' => 'success', 'message' => __('Stock movement posted.')]);
    }
}
