<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDocument;
use App\Models\ReplenishmentTarget;
use App\Models\User;
use App\Services\Documents\PurchaseWorkflow;
use App\Services\Supply\PreventiveDemandForecast;
use App\Services\Supply\SupplyScenarioPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplyControlTowerController extends Controller
{
    public function index(Request $request, SupplyScenarioPlanner $scenarioPlanner, PreventiveDemandForecast $preventiveDemand): Response
    {
        $materialRisk = $scenarioPlanner->forecast(today(), 45);
        $preventive = $preventiveDemand->forecast(today(), 180);
        if (! $request->user()->can('view-finance')) {
            $materialRisk['summary']['shortage_exposure'] = null;
            $materialRisk['summary']['late_inbound_value'] = null;
            $materialRisk['scenario']['recommended_net'] = null;
            $materialRisk['scenario']['recommended_cash'] = null;
            $materialRisk['scenario']['cash_within_30_days'] = null;
            $materialRisk['rows'] = collect($materialRisk['rows'])->map(fn (array $row) => [
                ...$row,
                'shortage_exposure' => null,
                'late_inbound_value' => null,
                'alternatives' => collect($row['alternatives'])->map(fn (array $alternative) => [
                    ...$alternative,
                    'unit_price' => null,
                    'net_cost' => null,
                    'cash_impact' => null,
                ])->all(),
                'recommended' => $row['recommended'] ? [
                    ...$row['recommended'],
                    'unit_price' => null,
                    'net_cost' => null,
                    'cash_impact' => null,
                ] : null,
            ])->all();
            $preventive['summary']['forecast_exposure'] = null;
            $preventive['rows'] = collect($preventive['rows'])->map(fn (array $row) => [...$row, 'forecast_exposure' => null])->all();
        }

        $orders = PurchaseDocument::query()
            ->with([
                'supplier:id,legal_name,trade_name,email',
                'destinationWarehouse:id,code,name',
                'assignee:id,name',
                'lines.product:id,track_stock',
            ])
            ->withCount(['qualityIncidents as open_quality_incidents_count' => fn ($query) => $query->where('status', 'open')])
            ->where('type', 'purchase_order')
            ->whereIn('status', ['ordered', 'partially_received'])
            ->get()
            ->map(function (PurchaseDocument $order): array {
                $stockLines = $order->lines->filter(fn ($line) => $line->product->track_stock);
                $ordered = (float) $stockLines->sum('quantity');
                $received = (float) $stockLines->sum('received_quantity');
                $eta = $order->supplier_confirmed_delivery_date ?: $order->expected_delivery_date;
                $daysUntilEta = $eta ? today()->diffInDays($eta, false) : null;
                $daysLate = $daysUntilEta !== null && $daysUntilEta < 0 ? abs($daysUntilEta) : 0;
                $followUpDue = $order->next_follow_up_date && $order->next_follow_up_date->lte(today());
                $priority = match (true) {
                    $daysLate > 0 => 'late',
                    $order->open_quality_incidents_count > 0 => 'quality',
                    $followUpDue => 'follow_up_due',
                    ! $order->supplier_confirmed_at => 'unconfirmed',
                    $daysUntilEta !== null && $daysUntilEta <= 7 => 'due_soon',
                    default => 'on_track',
                };

                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'status' => $order->status,
                    'issue_date' => $order->issue_date->toDateString(),
                    'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
                    'supplier_confirmed_delivery_date' => $order->supplier_confirmed_delivery_date?->toDateString(),
                    'supplier_confirmed_at' => $order->supplier_confirmed_at?->toIso8601String(),
                    'supplier_confirmation_reference' => $order->supplier_confirmation_reference,
                    'last_followed_up_at' => $order->last_followed_up_at?->toIso8601String(),
                    'last_follow_up_note' => $order->last_follow_up_note,
                    'next_follow_up_date' => $order->next_follow_up_date?->toDateString(),
                    'assigned_to' => $order->assigned_to,
                    'assigned_at' => $order->assigned_at?->toIso8601String(),
                    'assignee' => $order->assignee,
                    'supplier' => $order->supplier,
                    'destination_warehouse' => $order->destinationWarehouse,
                    'ordered_quantity' => round($ordered, 3),
                    'received_quantity' => round($received, 3),
                    'receipt_progress' => $ordered > 0 ? round($received / $ordered * 100, 1) : 100,
                    'open_quality_incidents' => $order->open_quality_incidents_count,
                    'days_late' => $daysLate,
                    'days_until_eta' => $daysUntilEta,
                    'priority' => $priority,
                ];
            })
            ->sortBy(fn (array $order) => [
                array_search($order['priority'], ['late', 'quality', 'follow_up_due', 'unconfirmed', 'due_soon', 'on_track'], true),
                $order['days_until_eta'] ?? 99999,
                $order['issue_date'],
            ])
            ->values();

        return Inertia::render('purchases/control-tower/index', [
            'orders' => $orders,
            'counts' => [
                'open' => $orders->count(),
                'late' => $orders->where('priority', 'late')->count(),
                'unconfirmed' => $orders->whereNull('supplier_confirmed_at')->count(),
                'due_soon' => $orders->where('priority', 'due_soon')->count(),
                'follow_up_due' => $orders->where('priority', 'follow_up_due')->count(),
                'unassigned' => $orders->whereNull('assigned_to')->count(),
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'materialRisk' => $materialRisk,
            'preventiveDemand' => $preventive,
            'replenishmentTargets' => ReplenishmentTarget::query()
                ->with(['product:id,sku,name', 'approver:id,name'])
                ->where('status', 'approved')->whereDate('period_ends_on', '>=', today())->latest('approved_at')->limit(100)->get(),
            'canApprovePreventiveDemand' => $request->user()->can('manage-catalog'),
        ]);
    }

    public function assign(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'next_follow_up_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $purchaseDocument, $workflow, $data): void {
            $order = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            abort_unless($order->type === 'purchase_order' && in_array($order->status, ['ordered', 'partially_received'], true), 422);
            $previousAssignee = $order->assigned_to;
            $assigneeId = $data['assigned_to'] ?? null;
            $order->update([
                'assigned_to' => $assigneeId,
                'assigned_by' => $assigneeId ? $request->user()->id : null,
                'assigned_at' => $assigneeId ? now() : null,
                'next_follow_up_date' => $data['next_follow_up_date'] ?? $order->next_follow_up_date,
                'last_follow_up_note' => $data['note'] ?? $order->last_follow_up_note,
            ]);
            $workflow->record($order, 'supply_action_assigned', $request->user()->id, $order->status, $order->status, [
                'from' => $previousAssignee,
                'to' => $order->assigned_to,
                'next_follow_up_date' => $order->next_follow_up_date?->toDateString(),
                'note' => $data['note'] ?? null,
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Supply action assignment updated.')]);
    }

    public function confirm(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && in_array($purchaseDocument->status, ['ordered', 'partially_received'], true), 422);
        $data = $request->validate([
            'confirmed_delivery_date' => ['required', 'date'],
            'confirmation_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'next_follow_up_date' => ['nullable', 'date'],
        ]);

        $order = DB::transaction(function () use ($request, $purchaseDocument, $data) {
            $order = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            abort_unless($order->type === 'purchase_order' && in_array($order->status, ['ordered', 'partially_received'], true), 422);
            abort_if($order->supplier_confirmed_at, 422, __('Use supplier follow-up to revise an existing confirmation.'));
            $order->update([
                'assigned_to' => $order->assigned_to ?? $request->user()->id,
                'assigned_by' => $order->assigned_to ? $order->assigned_by : $request->user()->id,
                'assigned_at' => $order->assigned_to ? $order->assigned_at : now(),
                'supplier_confirmed_delivery_date' => $data['confirmed_delivery_date'],
                'supplier_confirmed_at' => now(),
                'supplier_confirmed_by' => $request->user()->id,
                'supplier_confirmation_reference' => $data['confirmation_reference'] ?? null,
                'last_followed_up_at' => now(),
                'last_followed_up_by' => $request->user()->id,
                'last_follow_up_note' => $data['note'] ?? null,
                'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
            ]);

            return $order;
        });

        $workflow->record($order, 'supplier_delivery_confirmed', $request->user()->id, $order->status, $order->status, [
            'confirmed_delivery_date' => $data['confirmed_delivery_date'],
            'reference' => $data['confirmation_reference'] ?? null,
            'note' => $data['note'] ?? null,
            'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier delivery confirmed.')]);
    }

    public function followUp(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && in_array($purchaseDocument->status, ['ordered', 'partially_received'], true), 422);
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'confirmed_delivery_date' => ['nullable', 'date'],
            'confirmation_reference' => ['nullable', 'string', 'max:255'],
            'next_follow_up_date' => ['nullable', 'date'],
        ]);

        [$order, $previousEta] = DB::transaction(function () use ($request, $purchaseDocument, $data) {
            $order = PurchaseDocument::lockForUpdate()->findOrFail($purchaseDocument->id);
            abort_unless($order->type === 'purchase_order' && in_array($order->status, ['ordered', 'partially_received'], true), 422);
            abort_unless($order->supplier_confirmed_at, 422, __('Record the supplier confirmation first.'));
            $previousEta = $order->supplier_confirmed_delivery_date?->toDateString();
            $order->update([
                'assigned_to' => $order->assigned_to ?? $request->user()->id,
                'assigned_by' => $order->assigned_to ? $order->assigned_by : $request->user()->id,
                'assigned_at' => $order->assigned_to ? $order->assigned_at : now(),
                'supplier_confirmed_delivery_date' => $data['confirmed_delivery_date'] ?? $previousEta,
                'supplier_confirmation_reference' => $data['confirmation_reference'] ?? $order->supplier_confirmation_reference,
                'last_followed_up_at' => now(),
                'last_followed_up_by' => $request->user()->id,
                'last_follow_up_note' => $data['note'],
                'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
            ]);

            return [$order, $previousEta];
        });

        $workflow->record($order, 'supplier_followed_up', $request->user()->id, $order->status, $order->status, [
            'from' => $previousEta,
            'to' => $order->supplier_confirmed_delivery_date?->toDateString(),
            'reference' => $order->supplier_confirmation_reference,
            'note' => $data['note'],
            'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier follow-up recorded.')]);
    }
}
