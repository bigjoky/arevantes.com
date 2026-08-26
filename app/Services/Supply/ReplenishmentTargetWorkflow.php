<?php

namespace App\Services\Supply;

use App\Models\Product;
use App\Models\ReplenishmentTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplenishmentTargetWorkflow
{
    public function __construct(private PreventiveDemandForecast $forecast) {}

    public function approve(Product $product, float $targetQuantity, string $note, User $actor): ReplenishmentTarget
    {
        $active = ReplenishmentTarget::query()
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->whereDate('period_ends_on', '>=', today())
            ->latest('approved_at')
            ->first();
        if ($active) {
            return $active;
        }

        $forecast = $this->forecast->forecast(today(), 180);
        $row = collect($forecast['rows'])->firstWhere('product_id', $product->id);
        if (! $row || $row['forecast_shortage'] <= 0) {
            throw ValidationException::withMessages(['target_quantity' => __('This product has no current preventive-service shortage to approve.')]);
        }
        if ($targetQuantity + 0.0001 < (float) $row['forecast_shortage']) {
            throw ValidationException::withMessages(['target_quantity' => __('The objective cannot be below the current shortage of :quantity.', ['quantity' => $row['forecast_shortage']])]);
        }

        return DB::transaction(function () use ($product, $targetQuantity, $note, $actor, $forecast, $row): ReplenishmentTarget {
            $existing = ReplenishmentTarget::query()
                ->where('product_id', $product->id)
                ->whereDate('period_starts_on', $forecast['period']['from'])
                ->whereDate('period_ends_on', $forecast['period']['to'])
                ->first();
            if ($existing) {
                return $existing;
            }

            return ReplenishmentTarget::create([
                'product_id' => $product->id,
                'period_starts_on' => $forecast['period']['from'],
                'period_ends_on' => $forecast['period']['to'],
                'status' => 'approved',
                'target_quantity' => round($targetQuantity, 3),
                'expected_quantity' => $row['expected_quantity'],
                'minimum_stock' => $row['minimum_stock'],
                'free_stock' => $row['free_stock'],
                'inbound_quantity' => $row['inbound_before_horizon'],
                'forecast_shortage' => $row['forecast_shortage'],
                'source_snapshot' => ['plans' => $row['plans'], 'forecast_generated_at' => now()->toIso8601String()],
                'approval_note' => trim($note),
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
        });
    }
}
