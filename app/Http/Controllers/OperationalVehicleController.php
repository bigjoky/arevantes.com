<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\OperationalVehicle;
use App\Models\OperationalVehicleAssignmentHistory;
use App\Models\OperationalVehicleBlock;
use App\Models\WorkOrder;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\VehicleAvailability;
use App\Services\Work\WorkOrderFactory;
use App\Support\Time\BusinessTime;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OperationalVehicleController extends Controller
{
    public function index(Request $request)
    {
        $manage = $request->user()->can('manage-installations');
        $now = BusinessTime::now();
        $assigned = WorkOrder::whereNotNull('vehicle_id')->whereNotIn('status', ['completed', 'cancelled'])->whereInstant('scheduled_start', '<=', $now)->whereInstant('scheduled_end', '>', $now)->pluck('vehicle_id')->all();
        $activityAssigned = CrmActivity::whereNotNull('vehicle_id')->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])->whereInstant('starts_at', '<=', $now)->whereInstant('due_at', '>', $now)->pluck('vehicle_id')->all();
        $assigned = array_map('intval', array_unique([...$assigned, ...$activityAssigned]));
        $blocked = OperationalVehicleBlock::whereNull('cancelled_at_utc')->where('starts_at_utc', '<=', $now->copy()->utc())->where('ends_at_utc', '>', $now->copy()->utc())->pluck('vehicle_id')->map(fn ($id) => (int) $id)->all();
        $vehicles = OperationalVehicle::orderBy('name')->get()->map(fn (OperationalVehicle $vehicle) => [
            ...$vehicle->only(['id', 'registration', 'name', 'make', 'model', 'type', 'status', 'base_location']),
            'current_state' => $vehicle->status !== 'available' ? $vehicle->status : (in_array($vehicle->id, $blocked, true) ? 'blocked' : (in_array($vehicle->id, $assigned, true) ? 'assigned' : 'available')),
            'notes' => $manage ? $vehicle->notes : null,
            'blocks' => $manage ? $vehicle->blocks()->orderBy('starts_at_utc')->get(['id', 'vehicle_id', 'starts_at_utc', 'ends_at_utc', 'reason', 'cancelled_at_utc', 'cancellation_reason']) : [],
        ]);
        $orders = WorkOrder::query()->whereNotNull('vehicle_id')->whereNotIn('status', ['completed', 'cancelled'])
            ->when(! $manage, fn ($q) => $q->where('assigned_user_id', $request->user()->id))
            ->with(['vehicle:id,registration,name', 'assignedUser:id,name'])
            ->orderBy('scheduled_start')->limit(200)
            ->get(['id', 'number', 'title', 'status', 'vehicle_id', 'installation_id', 'assigned_user_id', 'scheduled_start', 'scheduled_end']);

        $visits = CrmActivity::whereNotNull('vehicle_id')->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])
            ->when(! $manage, fn ($q) => $q->where('assigned_user_id', $request->user()->id))->with(['vehicle:id,registration,name', 'assignedUser:id,name'])
            ->orderBy('starts_at')->limit(200)->get(['id', 'title', 'type', 'status', 'vehicle_id', 'assigned_user_id', 'starts_at', 'due_at']);

        return Inertia::render('operations/vehicles', ['vehicles' => $vehicles, 'orders' => $orders, 'visits' => $visits, 'canManage' => $manage, 'timezone' => BusinessTime::zone()]);
    }

    public function availability(Request $request, VehicleAvailability $availability)
    {
        $data = $request->validate(['starts_at' => ['required', 'string'], 'ends_at' => ['required', 'string'], 'except_activity_id' => ['nullable', 'integer', 'exists:crm_activities,id'], 'assignee_id' => ['nullable', 'integer', 'exists:users,id'], 'installation_id' => ['nullable', 'integer', 'exists:installations,id'], 'kind' => ['nullable', Rule::in(['survey', 'installation', 'commissioning', 'maintenance', 'repair'])], 'work_order_template_id' => ['nullable', 'integer', 'exists:work_order_templates,id']]);
        if (! empty($data['except_activity_id'])) {
            $activity = CrmActivity::findOrFail($data['except_activity_id']);
            abort_unless($request->user()->can('manage-installations') || $activity->assigned_user_id === $request->user()->id, 403);
        }
        $start = BusinessTime::parse($data['starts_at'], 'starts_at');
        $end = BusinessTime::parse($data['ends_at'], 'ends_at');

        $assignee = null;
        if (! empty($data['assignee_id'])) {
            $skills = [];
            $zone = null;
            if (! empty($data['installation_id'])) {
                $installation = Installation::findOrFail($data['installation_id']);
                $prepared = app(WorkOrderFactory::class)->prepare(['installation_id' => $installation->id, 'kind' => $data['kind'] ?? 'installation', 'work_order_template_id' => $data['work_order_template_id'] ?? null]);
                $skills = $prepared['required_skills'] ?? [];
                $zone = $installation->site_postal_code;
            }
            $assignee = app(TechnicianSchedule::class)->vehicleAwareAvailability((int) $data['assignee_id'], $start, $end, requiredSkills: $skills, serviceZone: $zone, exceptActivityId: $data['except_activity_id'] ?? null);
        }

        return response()->json(['assignee' => $assignee, 'vehicles' => OperationalVehicle::orderBy('name')->get(['id', 'name', 'registration', 'status'])->map(fn ($vehicle) => [
            ...$vehicle->toArray(), ...$availability->decision($vehicle->id, $start, $end, exceptActivityId: $data['except_activity_id'] ?? null),
        ])]);
    }

    public function history(Request $request, OperationalVehicle $vehicle)
    {
        $manage = $request->user()->can('manage-installations');
        $rows = OperationalVehicleAssignmentHistory::query()
            ->where(fn ($q) => $q->where('old_vehicle_id', $vehicle->id)->orWhere('new_vehicle_id', $vehicle->id))
            ->when(! $manage, fn ($q) => $q->where(fn ($q) => $q->whereHas('workOrder', fn ($w) => $w->where('assigned_user_id', $request->user()->id))
                ->orWhereHas('activity', fn ($a) => $a->where('assigned_user_id', $request->user()->id))))
            ->with(['actor:id,name', 'previousVehicle:id,registration,name', 'nextVehicle:id,registration,name', 'previousAssignee:id,name', 'nextAssignee:id,name', 'workOrder:id,number,title', 'activity:id,title'])
            ->latest('id')->limit(100)->get();

        return response()->json(['history' => $rows, 'truncated' => $rows->count() === 100]);
    }

    public function activityHistory(Request $request, CrmActivity $activity)
    {
        abort_unless($request->user()->can('manage-installations') || $activity->assigned_user_id === $request->user()->id, 403);

        return response()->json(['history' => $activity->vehicleHistory()->with(['actor:id,name', 'previousVehicle:id,registration,name', 'nextVehicle:id,registration,name', 'previousAssignee:id,name', 'nextAssignee:id,name'])->limit(50)->get()]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, OperationalVehicle $vehicle)
    {
        return $this->save($request, $vehicle);
    }

    private function save(Request $request, ?OperationalVehicle $vehicle = null)
    {
        $raw = trim((string) $request->input('registration'));
        $key = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $raw));
        $request->merge(['registration' => strtoupper($raw), 'registration_key' => $key]);
        $data = $request->validate([
            'registration' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9 -]+$/'],
            'registration_key' => ['required', 'string', 'max:40', Rule::unique('operational_vehicles')->ignore($vehicle?->id)],
            'name' => ['required', 'string', 'max:120'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['available', 'maintenance', 'out_of_service'])],
            'base_location' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'confirm_conflicts' => ['sometimes', 'boolean'],
        ]);
        try {
            DB::transaction(function () use ($request, $vehicle, $data) {
                if ($vehicle) {
                    $locked = OperationalVehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
                    $confirm = (bool) ($data['confirm_conflicts'] ?? false);
                    if ($locked->status === 'available' && $data['status'] !== 'available' && ! $confirm) {
                        $now = BusinessTime::now();
                        $hasOrders = WorkOrder::where('vehicle_id', $locked->id)->whereNotIn('status', ['completed', 'cancelled'])->where(fn ($q) => $q->whereInstant('scheduled_end', '>', $now)->orWhereNull('scheduled_end'))->exists();
                        $hasVisits = CrmActivity::where('vehicle_id', $locked->id)->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])->where(fn ($q) => $q->whereInstant('due_at', '>', $now)->orWhereNull('due_at'))->exists();
                        if ($hasOrders || $hasVisits) {
                            throw ValidationException::withMessages(['confirm_conflicts' => __('Review and confirm the affected vehicle assignments before changing its status.')]);
                        }
                    }
                    unset($data['confirm_conflicts']);
                    $locked->update($data);
                } else {
                    unset($data['confirm_conflicts']);
                    OperationalVehicle::create([...$data, 'created_by' => $request->user()->id]);
                }
            });
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[0] ?? '', ['23505', '23000'], true)) {
                throw ValidationException::withMessages(['registration' => __('This registration is already in use.')]);
            }
            throw $e;
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Vehicle saved.')]);
    }

    public function cancelBlock(Request $request, OperationalVehicle $vehicle, OperationalVehicleBlock $block, VehicleAvailability $availability)
    {
        abort_unless($block->vehicle_id === $vehicle->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $vehicle, $block, $availability, $data) {
            $availability->lockVehicles([$vehicle->id]);
            $locked = OperationalVehicleBlock::whereKey($block->id)->lockForUpdate()->firstOrFail();
            if (! $locked->cancelled_at_utc) {
                $locked->update(['cancelled_at_utc' => BusinessTime::now()->utc(), 'cancelled_by' => $request->user()->id, 'cancellation_reason' => $data['reason']]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Vehicle block cancelled.')]);
    }

    public function block(Request $request, OperationalVehicle $vehicle, VehicleAvailability $availability)
    {
        $data = $request->validate(['starts_at' => ['required', 'string'], 'ends_at' => ['required', 'string'], 'reason' => ['required', 'string', 'max:1000']]);
        $start = BusinessTime::parse($data['starts_at'], 'starts_at');
        $end = BusinessTime::parse($data['ends_at'], 'ends_at');
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['ends_at' => __('The end must be after the start.')]);
        }
        DB::transaction(function () use ($request, $vehicle, $availability, $start, $end, $data) {
            $availability->lockVehicles([$vehicle->id]);
            $incompleteOrders = WorkOrder::where('vehicle_id', $vehicle->id)->whereNotIn('status', ['completed', 'cancelled'])->where(fn ($q) => $q->whereNull('scheduled_start')->orWhereNull('scheduled_end'))->exists();
            $incompleteVisits = CrmActivity::where('vehicle_id', $vehicle->id)->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])->where(fn ($q) => $q->whereNull('starts_at')->orWhereNull('due_at'))->exists();
            if ($incompleteOrders || $incompleteVisits) {
                throw ValidationException::withMessages(['starts_at' => __('An existing vehicle assignment has an incomplete schedule.')]);
            }
            if (CrmActivity::where('vehicle_id', $vehicle->id)->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])->whereInstant('starts_at', '<', $end)->whereInstant('due_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => __('Replan conflicting work before blocking this vehicle.')]);
            }
            if (WorkOrder::where('vehicle_id', $vehicle->id)->whereNotIn('status', ['completed', 'cancelled'])->whereInstant('scheduled_start', '<', $end)->whereInstant('scheduled_end', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => __('Replan conflicting work before blocking this vehicle.')]);
            }
            if ($vehicle->blocks()->whereNull('cancelled_at_utc')->where('starts_at_utc', $start->copy()->utc())->where('ends_at_utc', $end->copy()->utc())->where('reason', $data['reason'])->where('created_by', $request->user()->id)->exists()) {
                return;
            }
            $vehicle->blocks()->create(['starts_at_utc' => $start->copy()->utc(), 'ends_at_utc' => $end->copy()->utc(), 'reason' => $data['reason'], 'created_by' => $request->user()->id]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Vehicle block recorded.')]);
    }
}
