<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\Operations\OperationsWorkspace;
use Illuminate\Http\Request;
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

    public function orders(Request $r, OperationsWorkspace $w)
    {
        if ($r->filled('work_order')) {
            return to_route('work-orders.show', ['workOrder' => $r->integer('work_order')]);
        }

        return Inertia::render('work-orders/list', $w->listing($r));
    }

    public function create(Request $r, OperationsWorkspace $w)
    {
        $filters = $r->validate(['installation' => ['nullable', 'integer', 'exists:installations,id'], 'q' => ['nullable', 'string', 'max:150']]);
        $installations = Installation::query()->where('status', '!=', 'cancelled')->when($filters['installation'] ?? null, fn ($q, $id) => $q->whereKey($id))->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($s) => $s->where('project_reference', 'like', '%'.$search.'%')->orWhere('site_name', 'like', '%'.$search.'%')))->orderByDesc('id')->limit(100)->get(['id', 'project_reference', 'site_name', 'service_type']);

        return Inertia::render('work-orders/create', [...$w->common($r), 'installations' => $installations, 'selectedInstallation' => $filters['installation'] ?? null, 'templates' => WorkOrderTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind', 'service_type'])]);
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
