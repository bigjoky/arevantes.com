<?php

namespace App\Services\Supply;

use App\Models\InstallationMaterialRequirement;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CriticalMaterialRisk
{
    /** @return array<string, mixed> */
    public function forecast(CarbonInterface $from, int $days = 45, ?User $viewer = null): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = $start->addDays(max(1, min($days, 180)))->endOfDay();
        $orders = WorkOrder::query()
            ->with('installation:id,project_reference,site_name')
            ->whereIn('status', ['planned', 'in_progress', 'blocked'])
            ->whereNotNull('scheduled_start')
            ->whereBetween('scheduled_start', [$start, $end])
            ->when($viewer?->hasRole('technician'), fn ($query) => $query->where('assigned_user_id', $viewer->id))
            ->orderBy('scheduled_start')
            ->get(['id', 'installation_id', 'assigned_user_id', 'number', 'title', 'scheduled_start'])
            ->unique('installation_id')
            ->values();

        if ($orders->isEmpty()) {
            return $this->emptyForecast($start, $end);
        }

        $installationIds = $orders->pluck('installation_id');
        $requirements = InstallationMaterialRequirement::query()
            ->with('product:id,sku,name,cost_price,supply_criticality,preferred_lead_time_days')
            ->whereIn('installation_id', $installationIds)
            ->whereNotNull('product_id')
            ->get();
        $productIds = $requirements->pluck('product_id')->unique()->values();
        $consumed = StockMovementLine::query()
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->whereIn('stock_movements.installation_id', $installationIds)
            ->whereIn('stock_movement_lines.product_id', $productIds)
            ->where('stock_movements.type', 'issue')
            ->where('stock_movement_lines.quantity', '<', 0)
            ->groupBy('stock_movements.installation_id', 'stock_movement_lines.product_id')
            ->selectRaw('stock_movements.installation_id, stock_movement_lines.product_id, sum(-stock_movement_lines.quantity) quantity')
            ->get()
            ->keyBy(fn ($row) => $this->key($row->installation_id, $row->product_id));
        $onHand = StockMovementLine::query()
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity) quantity')
            ->pluck('quantity', 'product_id');
        $allReserved = StockReservation::query()
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) quantity')
            ->pluck('quantity', 'product_id');
        $projectReserved = StockReservation::query()
            ->whereIn('installation_id', $installationIds)
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->groupBy('installation_id', 'product_id')
            ->selectRaw('installation_id, product_id, sum(quantity - consumed_quantity) quantity')
            ->get()
            ->keyBy(fn ($row) => $this->key($row->installation_id, $row->product_id));
        $inbound = DB::table('purchase_document_lines')
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->where('purchase_documents.type', 'purchase_order')
            ->whereIn('purchase_documents.status', ['ordered', 'partially_received'])
            ->whereIn('purchase_documents.installation_id', $installationIds)
            ->whereIn('purchase_document_lines.product_id', $productIds)
            ->select([
                'purchase_documents.id',
                'purchase_documents.number',
                'purchase_documents.installation_id',
                'purchase_documents.expected_delivery_date',
                'purchase_documents.supplier_confirmed_delivery_date',
                'purchase_document_lines.product_id',
                'purchase_document_lines.quantity',
                'purchase_document_lines.received_quantity',
                'purchase_document_lines.unit_price',
            ])
            ->get()
            ->groupBy(fn ($row) => $this->key($row->installation_id, $row->product_id));

        $freePool = $productIds->mapWithKeys(fn ($productId) => [
            $productId => max(0, (float) ($onHand[$productId] ?? 0) - (float) ($allReserved[$productId] ?? 0)),
        ])->all();
        $requirementsByInstallation = $requirements->groupBy('installation_id');
        $rows = collect();

        foreach ($orders as $order) {
            foreach ($requirementsByInstallation->get($order->installation_id, collect()) as $requirement) {
                $product = $requirement->product;
                if (! $product) {
                    continue;
                }
                $key = $this->key($order->installation_id, $product->id);
                $needed = max(0, (float) $requirement->planned_quantity - (float) ($consumed[$key]->quantity ?? 0));
                $reserved = min($needed, (float) ($projectReserved[$key]->quantity ?? 0));
                $free = min(max(0, $needed - $reserved), (float) ($freePool[$product->id] ?? 0));
                $freePool[$product->id] = max(0, (float) ($freePool[$product->id] ?? 0) - $free);
                $inboundRows = collect($inbound->get($key, collect()));
                $beforeDeadline = $inboundRows->sum(function ($line) use ($order): float {
                    $eta = $line->supplier_confirmed_delivery_date ?: $line->expected_delivery_date;

                    return $eta && CarbonImmutable::parse($eta)->lte($order->scheduled_start)
                        ? max(0, (float) $line->quantity - (float) $line->received_quantity)
                        : 0;
                });
                $lateInboundValue = $inboundRows->sum(function ($line) use ($order): float {
                    $eta = $line->supplier_confirmed_delivery_date ?: $line->expected_delivery_date;
                    $isLate = ! $eta || CarbonImmutable::parse($eta)->gt($order->scheduled_start);

                    return $isLate
                        ? max(0, (float) $line->quantity - (float) $line->received_quantity) * (float) $line->unit_price
                        : 0;
                });
                $shortage = max(0, $needed - $reserved - $free - $beforeDeadline);
                $daysUntilWork = (int) floor($start->diffInDays($order->scheduled_start, false));
                $criticality = $product->supply_criticality ?: 'standard';
                $severity = $this->severity($shortage, $criticality, $daysUntilWork, (int) $product->preferred_lead_time_days);

                if ($shortage <= 0 && $criticality !== 'critical' && $lateInboundValue <= 0) {
                    continue;
                }

                $unitCost = (float) ($requirement->planned_unit_cost ?: $product->cost_price);
                $rows->push([
                    'work_order_id' => $order->id,
                    'work_order_number' => $order->number,
                    'work_order_title' => $order->title,
                    'installation_id' => $order->installation_id,
                    'installation' => $order->installation?->project_reference ?: $order->installation?->site_name,
                    'scheduled_start' => $order->scheduled_start->toIso8601String(),
                    'days_until_work' => $daysUntilWork,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product' => $product->name,
                    'criticality' => $criticality,
                    'required_quantity' => round($needed, 3),
                    'reserved_quantity' => round($reserved, 3),
                    'free_quantity' => round($free, 3),
                    'inbound_before_work' => round($beforeDeadline, 3),
                    'shortage_quantity' => round($shortage, 3),
                    'shortage_exposure' => round($shortage * $unitCost, 2),
                    'late_inbound_value' => round($lateInboundValue, 2),
                    'severity' => $severity,
                ]);
            }
        }

        $rows = $rows->sortBy(fn (array $row) => [
            array_search($row['severity'], ['blocked', 'critical', 'warning', 'covered'], true),
            $row['scheduled_start'],
            -$row['shortage_exposure'],
        ])->values();

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'summary' => [
                'items' => $rows->count(),
                'critical_items' => $rows->where('criticality', 'critical')->count(),
                'blocked_work_orders' => $rows->where('severity', 'blocked')->pluck('work_order_id')->unique()->count(),
                'at_risk_work_orders' => $rows->whereIn('severity', ['blocked', 'critical', 'warning'])->pluck('work_order_id')->unique()->count(),
                'shortage_exposure' => round((float) $rows->sum('shortage_exposure'), 2),
                'late_inbound_value' => round((float) $rows->sum('late_inbound_value'), 2),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function severity(float $shortage, string $criticality, int $daysUntilWork, int $leadTime): string
    {
        if ($shortage <= 0) {
            return 'covered';
        }
        if ($criticality === 'critical' && $daysUntilWork <= max(1, $leadTime)) {
            return 'blocked';
        }
        if ($criticality === 'critical' || $daysUntilWork <= 7) {
            return 'critical';
        }

        return 'warning';
    }

    private function key(int|string $installationId, int|string $productId): string
    {
        return $installationId.'-'.$productId;
    }

    /** @return array<string, mixed> */
    private function emptyForecast(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'summary' => [
                'items' => 0,
                'critical_items' => 0,
                'blocked_work_orders' => 0,
                'at_risk_work_orders' => 0,
                'shortage_exposure' => 0.0,
                'late_inbound_value' => 0.0,
            ],
            'rows' => [],
        ];
    }
}
