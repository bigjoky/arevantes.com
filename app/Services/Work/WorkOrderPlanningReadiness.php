<?php

namespace App\Services\Work;

use App\Models\StockReservation;
use App\Models\WorkOrder;
use App\Services\Inventory\ProjectMaterialPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkOrderPlanningReadiness
{
    public function __construct(
        private ProjectMaterialPlanner $materials,
        private TechnicianCandidateFinder $technicians,
        private VehicleAvailability $vehicles,
    ) {}

    /** @return array<string, mixed> */
    public function for(WorkOrder $order): array
    {
        $order->loadMissing('installation');
        $plan = $this->materials->summary($order->installation);
        $requiresMaterials = in_array($order->kind, ['installation', 'repair'], true)
            && $plan['rows'] !== [];
        $orderReserved = $requiresMaterials ? StockReservation::query()->where('work_order_id', $order->id)->where('status', 'active')
            ->groupBy('product_id')->selectRaw('product_id, sum(quantity - consumed_quantity) quantity')->pluck('quantity', 'product_id') : collect();
        $deliveries = $requiresMaterials ? $this->deliveries($order) : collect();
        $scheduledDate = $order->scheduled_start?->toImmutable()->startOfDay();
        $rows = collect($plan['rows'])->map(function (array $row) use ($deliveries, $requiresMaterials, $scheduledDate, $orderReserved): array {
            if (! $requiresMaterials || ! $row['product_id'] || $row['is_unplanned']) {
                return [...$row, 'ready_on' => null, 'shortage_by_schedule' => 0.0, 'late_inbound_quantity' => 0.0];
            }

            $needed = (float) $row['remaining_quantity'];
            $reservedForOrder = (float) ($orderReserved[$row['product_id']] ?? 0);
            $covered = min($needed, (float) $row['available_quantity'] + $reservedForOrder);
            $readyOn = $covered >= $needed ? today()->toDateString() : null;
            $lateInbound = 0.0;
            foreach ($deliveries->get($row['product_id'], collect()) as $delivery) {
                $quantity = max(0, (float) $delivery->quantity - (float) $delivery->received_quantity);
                $eta = $delivery->supplier_confirmed_delivery_date ?: $delivery->expected_delivery_date;
                if (! $eta) {
                    $lateInbound += $quantity;

                    continue;
                }
                $date = CarbonImmutable::parse($eta)->startOfDay();
                $covered += $quantity;
                if ($readyOn === null && $covered >= $needed) {
                    $readyOn = $date->toDateString();
                }
                if ($scheduledDate && $date->gt($scheduledDate)) {
                    $lateInbound += $quantity;
                }
            }
            $coveredBySchedule = (float) $row['available_quantity'] + $reservedForOrder
                + $deliveries->get($row['product_id'], collect())->sum(function ($delivery) use ($scheduledDate): float {
                    $eta = $delivery->supplier_confirmed_delivery_date ?: $delivery->expected_delivery_date;

                    return $eta && (! $scheduledDate || CarbonImmutable::parse($eta)->startOfDay()->lte($scheduledDate))
                        ? max(0, (float) $delivery->quantity - (float) $delivery->received_quantity)
                        : 0;
                });

            return [...$row, 'reserved_for_order' => round($reservedForOrder, 3), 'ready_on' => $readyOn,
                'shortage_by_schedule' => round(max(0, $needed - $coveredBySchedule), 3),
                'late_inbound_quantity' => round($lateInbound, 3)];
        })->values();

        $qualified = $this->technicians->qualifiedFor($order);
        $assignedReady = $order->assigned_user_id === null
            ? false
            : collect($qualified)->contains('id', $order->assigned_user_id);
        $shortageItems = $rows->where('shortage_by_schedule', '>', 0)->count();
        $materialReady = ! $requiresMaterials || $shortageItems === 0;
        $materialReadyNow = ! $requiresMaterials || $rows->every(fn (array $row) => $row['is_unplanned'] || (float) $row['remaining_quantity'] <= (float) $row['available_quantity'] + (float) ($row['reserved_for_order'] ?? 0));
        $vehicle = $this->vehicles->decision($order->vehicle_id, $order->scheduled_start, $order->scheduled_end, $order->id, (bool) $order->vehicle_required);
        $vehicleReady = in_array($vehicle['status'], ['available', 'not_required'], true);
        $status = $materialReady && $assignedReady && $vehicleReady && $order->scheduled_start ? 'ready'
            : ($order->scheduled_start && (! $materialReady || ! $vehicleReady || ($order->assigned_user_id && ! $assignedReady)) ? 'blocked' : 'warning');

        return [
            'status' => $status,
            'material_required' => $requiresMaterials,
            'material_ready' => $materialReady,
            'material_ready_now' => $materialReadyNow,
            'material_ready_on' => $rows->pluck('ready_on')->filter()->sortDesc()->first(),
            'shortage_items' => $shortageItems,
            'shortage_quantity' => round((float) $rows->sum('shortage_by_schedule'), 3),
            'personnel_ready' => $assignedReady,
            'vehicle_ready' => $vehicleReady,
            'vehicle_status' => $vehicle['status'],
            'vehicle_issues' => $vehicle['issues'],
            'qualified_technicians' => $qualified,
            'required_skills' => $order->required_skills ?? [],
            'rows' => $rows->all(),
        ];
    }

    private function deliveries(WorkOrder $order)
    {
        return DB::table('purchase_document_lines')
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->where('purchase_documents.installation_id', $order->installation_id)
            ->where(fn ($query) => $query->where('purchase_documents.work_order_id', $order->id)->orWhereNull('purchase_documents.work_order_id'))
            ->where('purchase_documents.type', 'purchase_order')
            ->whereIn('purchase_documents.status', ['ordered', 'partially_received'])
            ->select(['purchase_document_lines.product_id', 'purchase_document_lines.quantity', 'purchase_document_lines.received_quantity', 'purchase_documents.expected_delivery_date', 'purchase_documents.supplier_confirmed_delivery_date'])
            ->orderByRaw('coalesce(purchase_documents.supplier_confirmed_delivery_date, purchase_documents.expected_delivery_date)')
            ->get()->groupBy('product_id');
    }
}
