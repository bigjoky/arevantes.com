<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\InstallationMaterialRequirement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaterialRequirementController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'installation_id' => ['required', Rule::exists('installations', 'id')->whereNot('status', 'cancelled')],
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)->where('track_stock', true)],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $product = Product::findOrFail($data['product_id']);
        abort_if($product->track_serials && floor((float) $data['planned_quantity']) !== (float) $data['planned_quantity'], 422);
        DB::transaction(function () use ($data, $product) {
            Installation::whereKey($data['installation_id'])->lockForUpdate()->firstOrFail();
            $existing = InstallationMaterialRequirement::where('installation_id', $data['installation_id'])->where('source', 'manual')->where('product_id', $data['product_id'])->first();
            if ($existing) {
                throw ValidationException::withMessages(['product_id' => __('This material requirement already exists.')]);
            }
            InstallationMaterialRequirement::create([...$data, 'source' => 'manual', 'sku' => $product->sku, 'description' => $product->name, 'planned_unit_cost' => $product->cost_price]);
        });

        return to_route('inventory.index', ['view' => 'needs', 'installation' => $data['installation_id']]);
    }
}
