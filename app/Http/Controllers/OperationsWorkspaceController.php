<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\OperationalVehicle;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\Operations\IndividualCalendar;
use App\Services\Operations\OperationsWorkspace;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\VehicleAvailability;
use App\Support\Time\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OperationsWorkspaceController extends Controller
{
    public function dashboard(Request $r, OperationsWorkspace $w)
    {
        if ($r->has('view')) {
            $target = in_array($r->input('view'), ['week', 'month'], true) ? 'operations.planning' : 'operations.activities';

            return to_route($target, $r->query());
        }
        if ($r->hasAny(['focus', 'planning_request', 'type', 'priority', 'q', 'status'])) {
            return to_route('operations.activities', $r->query());
        }

        return Inertia::render('operations/dashboard', $w->dashboard($r));
    }

    public function planning(Request $r, OperationsWorkspace $w)
    {
        return Inertia::render('operations/planning', $w->planning($r));
    }

    public function availability(Request $request, WorkOrder $workOrder, TechnicianSchedule $schedule)
    {
        $data = $request->validate(['scheduled_start' => ['required', 'string'], 'scheduled_end' => ['required', 'string'], 'vehicle_id' => ['nullable', 'integer', 'exists:operational_vehicles,id'], 'vehicle_required' => ['sometimes', 'boolean']]);
        $start = BusinessTime::parse($data['scheduled_start'], 'scheduled_start');
        $end = BusinessTime::parse($data['scheduled_end'], 'scheduled_end');

        $vehicles = OperationalVehicle::orderBy('name')->get(['id', 'name', 'registration', 'status'])->map(fn ($vehicle) => [...$vehicle->toArray(), ...app(VehicleAvailability::class)->decision($vehicle->id, $start, $end, $workOrder->id)]);

        return response()->json(['vehicles' => $vehicles, 'technicians' => User::whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name'])->map(fn ($user) => [
            'id' => $user->id, 'name' => $user->name,
            ...((! empty($data['vehicle_id']) || ! empty($data['vehicle_required'])) ? $schedule->vehicleAwareAvailability($user->id, $start, $end, $workOrder->id, $workOrder->required_skills ?? [], $workOrder->service_zone) : $schedule->availability($user->id, $start, $end, $workOrder->id, $workOrder->required_skills ?? [], $workOrder->service_zone)),
        ])]);
    }

    public function calendar(Request $request, User $user, IndividualCalendar $calendar)
    {
        abort_unless($request->user()->is($user) || $request->user()->can('manage-installations'), 403);

        return Inertia::render('operations/individual-calendar', $calendar->for($request, $user));
    }

    public function orders(Request $r, OperationsWorkspace $w)
    {
        if ($r->filled('work_order')) {
            return to_route('work-orders.show', ['workOrder' => $r->integer('work_order')]);
        }

        return Inertia::render('work-orders/list', $w->listing($r));
    }

    public function create(Request $r, OperationsWorkspace $w)
    {
        $filters = $r->validate(['kind' => ['nullable', Rule::in(['survey', 'installation', 'commissioning', 'maintenance', 'repair'])], 'installation' => ['nullable', 'integer', 'exists:installations,id'], 'q' => ['nullable', 'string', 'max:150']]);
        $installations = Installation::query()->where('status', '!=', 'cancelled')->when($filters['installation'] ?? null, fn ($q, $id) => $q->whereKey($id))->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($s) => $s->where('project_reference', 'like', '%'.$search.'%')->orWhere('site_name', 'like', '%'.$search.'%')))->orderByDesc('id')->limit(100)->get(['id', 'project_reference', 'site_name', 'service_type']);

        return Inertia::render('work-orders/create', [...$w->common($r), 'installations' => $installations, 'selectedInstallation' => $filters['installation'] ?? null, 'selectedKind' => $filters['kind'] ?? 'installation', 'templates' => WorkOrderTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind', 'service_type'])]);
    }

    public function show(Request $r, WorkOrder $workOrder, OperationsWorkspace $w)
    {
        return Inertia::render('work-orders/show', $w->detail($r, $workOrder));
    }

    public function agenda(Request $r, OperationsWorkspace $w)
    {
        return Inertia::render('operations/agenda', $w->agenda($r));
    }
}
