<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\SerialNumber;
use App\Models\ServiceSlaPolicy;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrderTemplate;
use App\Services\AfterSales\ServiceWorkGenerator;
use App\Services\AfterSales\SlaManager;
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
            'slaPolicies' => ServiceSlaPolicy::where('is_active', true)->orderBy('priority')->get(),
            'templates' => WorkOrderTemplate::where('is_active', true)->whereIn('kind', ['maintenance', 'repair'])->orderBy('name')->get(),
            'metrics' => [
                'open' => $tickets->whereNotIn('status', ['resolved', 'closed'])->count(),
                'response_breaches' => $tickets->whereNull('first_responded_at')->filter(fn (ServiceTicket $ticket) => $ticket->first_response_due_at?->isPast())->count(),
                'resolution_breaches' => $tickets->whereNotIn('status', ['resolved', 'closed'])->filter(fn (ServiceTicket $ticket) => $ticket->resolution_due_at?->isPast())->count(),
                'average_first_response_minutes' => round($tickets->filter(fn (ServiceTicket $ticket) => $ticket->first_responded_at)->avg(fn (ServiceTicket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_responded_at)) ?? 0),
                'average_resolution_minutes' => round($tickets->filter(fn (ServiceTicket $ticket) => $ticket->resolved_at)->avg(fn (ServiceTicket $ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at)) ?? 0),
                'recurrences' => $tickets->sum('reopened_count'),
                'satisfaction' => round($tickets->whereNotNull('satisfaction_rating')->avg('satisfaction_rating') ?? 0, 1),
            ],
            'canManage' => $request->user()->can('manage-installations'),
        ]);
    }

    public function storeTicket(Request $request, SlaManager $sla, ServiceWorkGenerator $work): RedirectResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'exists:installations,id'], 'assigned_user_id' => ['nullable', 'exists:users,id'], 'kind' => ['required', Rule::in(['incident', 'maintenance', 'warranty', 'inspection'])], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string']]);
        $ticket = ServiceTicket::create([...$data, 'created_by' => $request->user()->id, 'number' => $this->ticketNumber(), 'status' => 'open']);
        $sla->apply($ticket);
        $work->createForTicket($ticket, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Service ticket created.')]);
    }

    public function updateTicket(Request $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->ensureAccess($request, $ticket);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'assigned', 'in_progress', 'resolved', 'closed'])], 'assigned_user_id' => ['nullable', 'exists:users,id'], 'resolution' => ['nullable', 'string'], 'satisfaction_rating' => ['nullable', 'integer', 'between:1,5']]);
        if ($request->user()->hasRole('technician')) {
            $data['assigned_user_id'] = $request->user()->id;
        }
        abort_if(in_array($data['status'], ['resolved', 'closed'], true) && blank($data['resolution']), 422, __('Resolution is required.'));
        DB::transaction(function () use ($ticket, $data) {
            $ticket->update([
                ...$data,
                'first_responded_at' => $ticket->first_responded_at ?: (in_array($data['status'], ['assigned', 'in_progress', 'resolved', 'closed'], true) ? now() : null),
                'reopened_count' => in_array($ticket->status, ['resolved', 'closed'], true) && ! in_array($data['status'], ['resolved', 'closed'], true) ? $ticket->reopened_count + 1 : $ticket->reopened_count,
                'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
            ]);
            if ($ticket->maintenancePlan && in_array($data['status'], ['resolved', 'closed'], true)) {
                $ticket->maintenancePlan->update(['last_completed_on' => today(), 'next_due_on' => today()->addMonths($ticket->maintenancePlan->interval_months)]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Service ticket updated.')]);
    }

    public function createWorkOrder(Request $request, ServiceTicket $ticket, ServiceWorkGenerator $generator): RedirectResponse
    {
        $this->ensureAccess($request, $ticket);
        abort_if($ticket->work_order_id, 422, __('A work order already exists.'));
        $order = $generator->createForTicket($ticket, $request->user()->id);

        return to_route('work-orders.index', ['work_order' => $order->id])->with('toast', ['type' => 'success', 'message' => __('Work order created.')]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'exists:installations,id'], 'work_order_template_id' => ['nullable', 'exists:work_order_templates,id'], 'assigned_user_id' => ['nullable', 'exists:users,id'], 'name' => ['required', 'string', 'max:255'], 'interval_months' => ['required', 'integer', 'between:1,120'], 'lead_days' => ['nullable', 'integer', 'between:0,90'], 'starts_on' => ['required', 'date'], 'next_due_on' => ['required', 'date', 'after_or_equal:starts_on'], 'generate_work_order' => ['nullable', 'boolean'], 'scope' => ['nullable', 'string']]);
        MaintenancePlan::create([...$data, 'is_active' => true, 'generate_work_order' => $data['generate_work_order'] ?? true]);

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

    public function storeSlaPolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'customer_type' => ['nullable', 'string', 'max:30'],
            'first_response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
            'escalation_minutes' => ['required', 'integer', 'min:1'],
        ]);
        ServiceSlaPolicy::create([...$data, 'is_active' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => __('SLA policy created.')]);
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
