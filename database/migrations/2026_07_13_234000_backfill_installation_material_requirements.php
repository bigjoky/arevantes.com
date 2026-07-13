<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quotes')
            ->where('status', 'accepted')
            ->whereNotNull('installation_id')
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get()
            ->unique('installation_id')
            ->each(function ($quote): void {
                $now = now();
                $requirements = DB::table('quote_lines')
                    ->join('products', 'products.id', '=', 'quote_lines.product_id')
                    ->where('quote_lines.quote_id', $quote->id)
                    ->where('products.track_stock', true)
                    ->groupBy('quote_lines.product_id', 'products.sku', 'products.name', 'products.cost_price')
                    ->selectRaw('quote_lines.product_id, products.sku, products.name, products.cost_price, sum(quote_lines.quantity) as planned_quantity')
                    ->get()
                    ->map(fn ($line) => [
                        'installation_id' => $quote->installation_id,
                        'quote_id' => $quote->id,
                        'product_id' => $line->product_id,
                        'source' => 'quote',
                        'sku' => $line->sku,
                        'description' => $line->name,
                        'planned_quantity' => $line->planned_quantity,
                        'planned_unit_cost' => $line->cost_price,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($requirements !== []) {
                    DB::table('installation_material_requirements')->insertOrIgnore($requirements);
                }
            });
    }

    public function down(): void
    {
        // The forecast snapshots may have changed after deployment and should be preserved.
    }
};
