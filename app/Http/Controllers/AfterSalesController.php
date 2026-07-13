<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\SerialNumber;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Work\ChecklistTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AfterSalesController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = ServiceTicket::with(['installation:id,project_reference,site_name,service_type', 'assignedUser:id,name', 'workOrder:id,number,status'])->when($request->user()->hasRole('technician'), fn ($query) => $query->where(fn ($query) => $query->where('assigned_user_id', $request->user()->id)->orWhereNull('assigned_user_id')))->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->orderByRaw("case when status in ('resolved','closed') then 1 else 0 end")->latest()->get();

        return Inertia::render('after-sales/index', [
            'tickets' => $tickets,
            'plans' => MaintenancePlan::with('installation:id,project_reference,site_name')->orderBy('next_due_on')->get(),
            'installations' => Installation::with(['customer:id,first_name,last_name,company_name'])->orderBy('project_reference')->get(['id', 'person_id', 'project_reference', 'site_name', 'service_type', 'status']),
            'users' => User::whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name']),
            'warranties' => SerialNumber::with(['product:id,name,sku'])->whereNotNull('installation_id')->orderBy('warranty_ends_on')->get(),
            'canManage' => $request->user()->can('manage-installations'),
        ]);
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'exists:installations,id'], 'assigned_user_id' => ['nullable', 'exists:users,id'], 'kind' => ['required', Rule::in(['incident', 'maintenance', 'warranty', 'inspection'])], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string']]);
        ServiceTicket::create([...$data, 'created_by' => $request->user()->id, 'number' => $this->ticketNumber(), 'status' => 'open']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Service ticket created.')]);
    }

    public function updateTicket(Request $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->ensureAccess($request, $ticket);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'assigned', 'in_progress', 'resolved', 'closed'])], 'assigned_user_id' => ['nullable', 'exists:users,id'], 'resolution' => ['nullable', 'string']]);
        if ($request->user()->hasRole('technician')) {
            $data['assigned_user_id'] = $request->user()->id;
        }
        abort_if(in_array($data['status'], ['resolved', 'closed'], true) && blank($data['resolution']), 422, __('Resolution is required.'));
        DB::transaction(function () use ($ticket, $data) {
            $ticket->update([...$data, 'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null]);
            if ($ticket->maintenancePlan && in_array($data['status'], ['resolved', 'closed'], true)) {
                $ticket->maintenancePlan->update(['last_completed_on' => today(), 'next_due_on' => today()->addMonths($ticket->maintenancePlan->interval_months)]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Service ticket updated.')]);
    }

    public function createWorkOrder(Request $request, ServiceTicket $ticket, ChecklistTemplate $template): RedirectResponse
    {
        $this->ensureAccess($request, $ticket);
        abort_if($ticket->work_order_id, 422, __('A work order already exists.'));
        $order = DB::transaction(function () use ($request, $ticket, $template) {
            $order = WorkOrder::create(['installation_id' => $ticket->installation_id, 'assigned_user_id' => $ticket->assigned_user_id, 'created_by' => $request->user()->id, 'number' => 'WO-'.now()->format('Y').'-'.str_pad((string) (WorkOrder::count() + 1), 5, '0', STR_PAD_LEFT), 'title' => $ticket->title, 'status' => 'planned', 'instructions' => $ticket->description]);
            $order->checklistItems()->createMany(collect($template->for($ticket->installation->service_type))->map(fn ($item, $position) => [...$item, 'position' => $position + 1])->all());
            $ticket->update(['work_order_id' => $order->id, 'status' => $ticket->assigned_user_id ? 'assigned' : 'open']);

            return $order;
        });

        return to_route('work-orders.index', ['work_order' => $order->id])->with('toast', ['type' => 'success', 'message' => __('Work order created.')]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'exists:installations,id'], 'name' => ['required', 'string', 'max:255'], 'interval_months' => ['required', 'integer', 'between:1,120'], 'starts_on' => ['required', 'date'], 'next_due_on' => ['required', 'date', 'after_or_equal:starts_on'], 'scope' => ['nullable', 'string']]);
        MaintenancePlan::create([...$data, 'is_active' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Maintenance plan created.')]);
    }

    public function updatePlan(Request $request, MaintenancePlan $plan): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'next_due_on' => ['required', 'date']]);
        $plan->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Maintenance plan updated.')]);
    }

    public function updateWarranty(Request $request, SerialNumber $serial): RedirectResponse
    {
        abort_unless($serial->installation_id, 422);
        $data = $request->validate(['warranty_starts_on' => ['nullable', 'date'], 'warranty_ends_on' => ['nullable', 'date', 'after_or_equal:warranty_starts_on']]);
        $serial->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Warranty updated.')]);
    }

    private function ensureAccess(Request $request, ServiceTicket $ticket): void
    {
        if ($request->user()->hasRole('technician')) {
            abort_unless(! $ticket->assigned_user_id || $ticket->assigned_user_id === $request->user()->id, 403);
        }
    }

    private function ticketNumber(): string
    {
        return 'SAT-'.now()->format('Y').'-'.str_pad((string) (ServiceTicket::count() + 1), 5, '0', STR_PAD_LEFT);
    }
}
