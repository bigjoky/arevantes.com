<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderChecklistItem;
use App\Models\WorkOrderTemplate;
use App\Rules\SafeUpload;
use App\Services\Inventory\ProjectMaterialPlanner;
use App\Services\Operations\FinancialClearanceGuard;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderCompletionGuard;
use App\Services\Work\WorkOrderFactory;
use App\Services\Work\WorkOrderRescheduler;
use App\Services\Work\WorkOrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderController extends Controller
{
    public function index(Request $request, TechnicianSchedule $schedule, ProjectMaterialPlanner $materials): Response
    {
        $query = WorkOrder::with(['installation:id,project_reference,site_name,status,site_postal_code,site_latitude,site_longitude', 'installation.materialRequirements:id,installation_id', 'assignedUser:id,name', 'assignedUser.fieldServiceProfile', 'stockReservations:id,work_order_id,status', 'template:id,name,allowed_transitions'])->withSum('timeEntries as duration_minutes', 'duration_minutes')->withSum('timeEntries as travel_km', 'travel_km');
        if ($request->user()->hasRole('technician')) {
            $query->where('assigned_user_id', $request->user()->id);
        }
        $orders = $query->orderByRaw("case when status = 'completed' then 1 else 0 end")->orderBy('scheduled_start')->latest('id')->get();
        $planningIssues = $schedule->planningIssues($orders);
        $orders->each(function (WorkOrder $order) use ($planningIssues): void {
            $order->setAttribute('planning_issues', $planningIssues[$order->id] ?? []);
            $order->setAttribute('has_schedule_conflict', collect($planningIssues[$order->id] ?? [])->contains('type', 'overlap'));
            $order->setAttribute('planned_minutes', $order->scheduled_start && $order->scheduled_end ? $order->scheduled_start->diffInMinutes($order->scheduled_end) : $order->estimated_duration_minutes);
            $order->setAttribute('time_variance_minutes', $order->duration_minutes !== null && $order->estimated_duration_minutes !== null ? (int) $order->duration_minutes - $order->estimated_duration_minutes : null);
        });
        $selectedId = (int) $request->integer('work_order');
        $selected = $selectedId ? WorkOrder::with(['installation', 'assignedUser:id,name', 'timeEntries.user:id,name', 'checklistItems', 'attachments', 'stockReservations.product:id,sku,name,track_serials', 'stockReservations.warehouse:id,code,name', 'purchaseDocuments.supplier:id,legal_name,trade_name'])->find($selectedId) : null;
        if ($selected) {
            $this->ensureAccess($request, $selected);
            $selected->setAttribute('planning_issues', $planningIssues[$selected->id] ?? []);
            $selected->setAttribute('has_schedule_conflict', collect($planningIssues[$selected->id] ?? [])->contains('type', 'overlap'));
            $selected->setAttribute('material_plan', $materials->summary($selected->installation));
            $selected->setAttribute('completion_readiness', $this->completionReadiness($selected));
        }

        return Inertia::render('work-orders/index', [
            'workOrders' => $orders, 'selected' => $selected,
            'installations' => Installation::whereNotIn('status', ['completed', 'cancelled'])->orderBy('project_reference')->get(['id', 'project_reference', 'site_name']),
            'users' => User::with('fieldServiceProfile')->whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name', 'role']),
            'templates' => WorkOrderTemplate::where('is_active', true)->orderBy('name')->get(),
            'runningEntry' => TimeEntry::with('workOrder:id,number,title')->where('user_id', $request->user()->id)->whereNull('ended_at')->first(),
            'canManage' => $request->user()->can('manage-installations'), 'currentUserId' => $request->user()->id,
            'defaults' => ['hourlyCost' => config('work.default_hourly_cost'), 'mileageRate' => config('work.mileage_rate')],
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::where('track_stock', true)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'track_serials']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name']),
            'planningMetrics' => [
                'scheduled' => $orders->whereNotNull('scheduled_start')->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'unscheduled' => $orders->whereNull('scheduled_start')->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'conflicts' => collect($planningIssues)->filter(fn (array $issues) => collect($issues)->contains('type', 'overlap'))->count(),
                'planned_hours' => round($orders->whereNotIn('status', ['completed', 'cancelled'])->sum(fn (WorkOrder $order) => $order->scheduled_start && $order->scheduled_end ? $order->scheduled_start->diffInMinutes($order->scheduled_end) / 60 : 0), 1),
            ],
        ]);
    }

    public function store(Request $request, WorkOrderFactory $factory, TechnicianSchedule $schedule): RedirectResponse
    {
        $data = $this->validatedSchedule($request, true);
        $data['kind'] ??= 'installation';
        $schedule->assertAvailable(isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null, $data['scheduled_start'] ?? null, $data['scheduled_end'] ?? null, null, $data['required_skills'] ?? [], $data['service_zone'] ?? null);
        $order = $factory->create($data, $request->user()->id);
        $this->syncInstallation($order);

        return to_route('work-orders.index', ['work_order' => $order->id])->with('toast', ['type' => 'success', 'message' => __('Work order created.')]);
    }

    public function updateSchedule(Request $request, WorkOrder $workOrder, WorkOrderRescheduler $rescheduler, FinancialClearanceGuard $financialClearance): RedirectResponse
    {
        $data = $this->validatedSchedule($request);
        $financialClearance->assertAllows($workOrder->loadMissing('installation')->installation, $data['kind'] ?? $workOrder->kind);
        $reason = $request->validate(['reschedule_reason' => ['required', 'string', 'max:1000']])['reschedule_reason'];
        $rescheduler->reschedule($workOrder, $data, $reason, $request->user()->id);
        $this->syncInstallation($workOrder);

        return back()->with('toast', ['type' => 'success', 'message' => __('Work order schedule updated.')]);
    }

    public function bulkReschedule(Request $request, WorkOrderRescheduler $rescheduler): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.work_order_id' => ['required', 'exists:work_orders,id'],
            'items.*.assigned_user_id' => ['nullable', 'exists:users,id'],
            'items.*.scheduled_start' => ['required', 'date'],
            'items.*.scheduled_end' => ['required', 'date', 'after:items.*.scheduled_start'],
        ]);
        $batch = $rescheduler->bulk($data['items'], $data['reason'], $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Work orders rescheduled. Batch: :batch', ['batch' => $batch])]);
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderWorkflow $workflow, WorkOrderCompletionGuard $completion): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate(['status' => ['required', Rule::in(['planned', 'in_progress', 'blocked', 'completed', 'cancelled'])], 'completion_notes' => ['nullable', 'string']]);
        $workflow->assertTransition($workOrder->loadMissing('template'), $data['status']);
        if ($data['status'] === 'completed') {
            $completion->assertCanComplete($workOrder->loadMissing('installation'), $data['completion_notes'] ?? null);
        }
        $workOrder->update([...$data, 'completed_at' => $data['status'] === 'completed' ? now() : null]);
        $workOrder->activities()->update(['status' => $data['status'] === 'completed' ? 'completed' : ($data['status'] === 'cancelled' ? 'cancelled' : ($data['status'] === 'blocked' ? 'blocked' : 'in_progress')), 'completed_at' => $data['status'] === 'completed' ? now() : null]);
        $this->syncInstallation($workOrder);

        return back()->with('toast', ['type' => 'success', 'message' => __('Work order updated.')]);
    }

    public function start(Request $request, WorkOrder $workOrder, FinancialClearanceGuard $financialClearance): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        $financialClearance->assertAllows($workOrder->loadMissing('installation')->installation, $workOrder->kind);
        abort_if(TimeEntry::where('user_id', $request->user()->id)->whereNull('ended_at')->exists(), 422, __('You already have a running timer.'));
        abort_if(in_array($workOrder->status, ['completed', 'cancelled'], true), 422);
        TimeEntry::create(['work_order_id' => $workOrder->id, 'user_id' => $request->user()->id, 'created_by' => $request->user()->id, 'started_at' => now(), 'hourly_cost' => config('work.default_hourly_cost'), 'mileage_rate' => config('work.mileage_rate')]);
        $workOrder->update(['status' => 'in_progress']);
        $workOrder->activities()->update(['status' => 'in_progress']);
        $this->syncInstallation($workOrder);

        return back()->with('toast', ['type' => 'success', 'message' => __('Timer started.')]);
    }

    public function stop(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        abort_unless($timeEntry->user_id === $request->user()->id && ! $timeEntry->ended_at, 403);
        $data = $request->validate(['travel_km' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string']]);
        $ended = now();
        $minutes = max(1, (int) round($timeEntry->started_at->diffInMinutes($ended)));
        $timeEntry->update([...$data, 'ended_at' => $ended, 'duration_minutes' => $minutes]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Timer stopped.')]);
    }

    public function storeTime(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'started_at' => ['required', 'date'], 'ended_at' => ['required', 'date', 'after:started_at'], 'hourly_cost' => ['required', 'numeric', 'min:0'], 'travel_km' => ['nullable', 'numeric', 'min:0'], 'mileage_rate' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string']]);
        $start = Carbon::parse($data['started_at']);
        $end = Carbon::parse($data['ended_at']);
        $workOrder->timeEntries()->create([...$data, 'created_by' => $request->user()->id, 'duration_minutes' => max(1, (int) round($start->diffInMinutes($end)))]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Time entry added.')]);
    }

    public function destroyTime(TimeEntry $timeEntry): RedirectResponse
    {
        abort_if(! $timeEntry->ended_at, 422);
        $timeEntry->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Time entry deleted.')]);
    }

    public function toggleChecklist(Request $request, WorkOrder $workOrder, WorkOrderChecklistItem $item): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($item->work_order_id === $workOrder->id, 404);
        $data = $request->validate(['completed' => ['required', 'boolean'], 'notes' => ['nullable', 'string']]);
        $item->update(['completed_at' => $data['completed'] ? now() : null, 'completed_by' => $data['completed'] ? $request->user()->id : null, 'notes' => $data['notes'] ?? $item->notes]);

        return back();
    }

    public function storeAttachments(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate(['files' => ['required', 'array', 'min:1', 'max:12'], 'files.*' => ['file', new SafeUpload(['jpg', 'jpeg', 'png', 'webp', 'pdf'], 12288)], 'caption' => ['nullable', 'string', 'max:500']]);
        foreach ($data['files'] as $file) {
            $path = $file->store("work-orders/{$workOrder->id}", 'local');
            $workOrder->attachments()->create(['uploaded_by' => $request->user()->id, 'kind' => str_starts_with($file->getMimeType() ?: '', 'image/') ? 'photo' : 'document', 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(), 'caption' => $data['caption'] ?? null]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Evidence uploaded.')]);
    }

    public function downloadAttachment(Request $request, WorkOrder $workOrder, WorkOrderAttachment $attachment): StreamedResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($attachment->work_order_id === $workOrder->id && Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    public function destroyAttachment(Request $request, WorkOrder $workOrder, WorkOrderAttachment $attachment): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($attachment->work_order_id === $workOrder->id, 404);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Evidence deleted.')]);
    }

    public function sign(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate(['signed_by_name' => ['required', 'string', 'max:255'], 'signature' => ['required', 'string', 'max:1500000']]);
        abort_unless(preg_match('/^data:image\/png;base64,(.+)$/', $data['signature'], $matches), 422, __('Invalid signature data.'));
        $binary = base64_decode($matches[1], true);
        abort_if($binary === false || strlen($binary) > 750000 || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n"), 422, __('Invalid signature data.'));
        if ($workOrder->signature_path) {
            Storage::disk('local')->delete($workOrder->signature_path);
        }
        $path = "work-orders/{$workOrder->id}/signature.png";
        Storage::disk('local')->put($path, $binary);
        $workOrder->update(['signed_by_name' => $data['signed_by_name'], 'signature_path' => $path, 'signed_at' => now()]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Customer signature captured.')]);
    }

    public function signature(Request $request, WorkOrder $workOrder): StreamedResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($workOrder->signature_path && Storage::disk('local')->exists($workOrder->signature_path), 404);

        return Storage::disk('local')->download($workOrder->signature_path, "{$workOrder->number}-signature.png", ['Content-Type' => 'image/png', 'Content-Disposition' => 'inline']);
    }

    private function ensureAccess(Request $request, WorkOrder $workOrder): void
    {
        if ($request->user()->hasRole('technician')) {
            abort_unless($workOrder->assigned_user_id === $request->user()->id, 403);
        }
    }

    private function validatedSchedule(Request $request, bool $creating = false): array
    {
        if ($request->input('assigned_user_id') === 'none') {
            $request->merge(['assigned_user_id' => null]);
        }

        return $request->validate([
            'installation_id' => [$creating ? 'required' : 'sometimes', 'exists:installations,id'],
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['technician', 'admin']))],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::in(['survey', 'installation', 'commissioning', 'maintenance', 'repair'])],
            'work_order_template_id' => ['nullable', 'exists:work_order_templates,id'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'required_skills' => ['nullable', 'array', 'max:20'],
            'required_skills.*' => ['string', 'max:100'],
            'service_zone' => ['nullable', 'string', 'max:100'],
            'scheduled_start' => ['nullable', 'date', 'required_with:scheduled_end'],
            'scheduled_end' => ['nullable', 'date', 'required_with:scheduled_start', 'after:scheduled_start'],
            'instructions' => ['nullable', 'string'],
        ]);
    }

    private function syncActivity(WorkOrder $order, int $createdBy): void
    {
        $order->activities()->updateOrCreate([], [
            'installation_id' => $order->installation_id,
            'person_id' => $order->installation->person_id,
            'assigned_user_id' => $order->assigned_user_id,
            'created_by' => $createdBy,
            'type' => in_array($order->kind, ['survey', 'maintenance', 'repair'], true) ? 'visit' : 'task',
            'title' => $order->title,
            'notes' => __('Linked to work order :number.', ['number' => $order->number]),
            'priority' => $order->kind === 'repair' ? 'high' : 'normal',
            'status' => 'pending',
            'starts_at' => $order->scheduled_start,
            'due_at' => $order->scheduled_end,
        ]);
    }

    private function syncInstallation(WorkOrder $order): void
    {
        $openExecutionOrders = WorkOrder::query()
            ->where('installation_id', $order->installation_id)
            ->where('id', '!=', $order->id)
            ->whereIn('kind', ['installation', 'commissioning'])
            ->whereNotIn('status', ['completed', 'cancelled']);
        $completedExecutionStatus = (clone $openExecutionOrders)->exists()
            ? ((clone $openExecutionOrders)->where('status', 'in_progress')->exists() ? 'in_progress' : 'scheduled')
            : 'completed';
        $status = match (true) {
            $order->kind === 'survey' && $order->status === 'completed' => 'approved',
            $order->kind === 'survey' => 'survey_booked',
            in_array($order->kind, ['installation', 'commissioning'], true) && $order->status === 'completed' => $completedExecutionStatus,
            in_array($order->kind, ['installation', 'commissioning'], true) && $order->status === 'in_progress' => 'in_progress',
            in_array($order->kind, ['installation', 'commissioning'], true) && $order->scheduled_start => 'scheduled',
            default => null,
        };

        if ($status && $order->installation->status !== 'cancelled') {
            $order->installation->update(['status' => $status]);
        }
    }

    /** @return array<string, bool> */
    private function completionReadiness(WorkOrder $order): array
    {
        return [
            'timer_stopped' => ! $order->timeEntries()->whereNull('ended_at')->exists(),
            'checklist_complete' => ! $order->checklistItems()->where('is_required', true)->whereNull('completed_at')->exists(),
            'has_signature' => (bool) $order->signed_at,
            'has_photo' => $order->attachments()->where('kind', 'photo')->exists(),
            'has_observations' => filled($order->completion_notes),
        ];
    }
}
