<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updated_after' => ['nullable', 'date'],
            'status' => ['nullable', 'in:planned,in_progress,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $orders = $this->accessible($request)
            ->with(['installation:id,project_reference,site_name,site_address_line_1,site_city,site_postal_code,site_latitude,site_longitude', 'assignedUser:id,name', 'checklistItems'])
            ->when($validated['updated_after'] ?? null, fn (Builder $query, string $date) => $query->where('updated_at', '>', $date))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByRaw("case when status = 'completed' then 1 else 0 end")
            ->orderBy('scheduled_start')
            ->paginate($validated['per_page'] ?? 30);

        return response()->json([
            'data' => collect($orders->items())->map(fn (WorkOrder $order) => $this->summary($order)),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'per_page' => $orders->perPage(), 'total' => $orders->total()],
            'running_time_entry' => ($entry = TimeEntry::with('workOrder:id,number,title')->where('user_id', $request->user()->id)->whereNull('ended_at')->first()) ? [
                'id' => $entry->id,
                'started_at' => $entry->started_at->toISOString(),
                'work_order_id' => $entry->work_order_id,
            ] : null,
        ]);
    }

    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $workOrder->load(['installation', 'assignedUser:id,name', 'timeEntries.user:id,name', 'checklistItems', 'attachments']);

        return response()->json(['data' => [...$this->summary($workOrder),
            'instructions' => $workOrder->instructions,
            'completion_notes' => $workOrder->completion_notes,
            'signed_by_name' => $workOrder->signed_by_name,
            'signed_at' => $workOrder->signed_at?->toISOString(),
            'checklist' => $workOrder->checklistItems->map(fn ($item) => [
                'id' => $item->id, 'position' => $item->position, 'label' => $item->label,
                'is_required' => $item->is_required, 'completed_at' => $item->completed_at?->toISOString(), 'notes' => $item->notes,
            ]),
            'time_entries' => $workOrder->timeEntries->map(fn ($entry) => [
                'id' => $entry->id, 'started_at' => $entry->started_at->toISOString(), 'ended_at' => $entry->ended_at?->toISOString(),
                'duration_minutes' => $entry->duration_minutes, 'travel_km' => (float) $entry->travel_km, 'notes' => $entry->notes,
            ]),
            'attachments' => $workOrder->attachments->map(fn ($attachment) => [
                'id' => $attachment->id, 'kind' => $attachment->kind, 'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type, 'size' => $attachment->size, 'caption' => $attachment->caption,
            ]),
        ]]);
    }

    public function startTimer(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_if(TimeEntry::where('user_id', $request->user()->id)->whereNull('ended_at')->exists(), 422, __('You already have a running timer.'));
        abort_if(in_array($workOrder->status, ['completed', 'cancelled'], true), 422);
        $entry = TimeEntry::create(['work_order_id' => $workOrder->id, 'user_id' => $request->user()->id, 'created_by' => $request->user()->id, 'started_at' => now(), 'hourly_cost' => config('work.default_hourly_cost'), 'mileage_rate' => config('work.mileage_rate')]);
        $workOrder->update(['status' => 'in_progress']);

        return response()->json(['data' => ['id' => $entry->id, 'started_at' => $entry->started_at->toISOString()]], 201);
    }

    public function stopTimer(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        abort_unless($timeEntry->user_id === $request->user()->id && ! $timeEntry->ended_at, 403);
        $data = $request->validate(['travel_km' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string']]);
        $endedAt = now();
        $timeEntry->update([...$data, 'ended_at' => $endedAt, 'duration_minutes' => max(1, (int) round($timeEntry->started_at->diffInMinutes($endedAt)))]);

        return response()->json(['data' => ['id' => $timeEntry->id, 'ended_at' => $endedAt->toISOString(), 'duration_minutes' => $timeEntry->duration_minutes]]);
    }

    public function updateChecklist(Request $request, WorkOrder $workOrder, WorkOrderChecklistItem $item): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($item->work_order_id === $workOrder->id, 404);
        $data = $request->validate(['completed' => ['required', 'boolean'], 'notes' => ['nullable', 'string']]);
        $item->update(['completed_at' => $data['completed'] ? now() : null, 'completed_by' => $data['completed'] ? $request->user()->id : null, 'notes' => $data['notes'] ?? $item->notes]);

        return response()->json(['data' => ['id' => $item->id, 'completed_at' => $item->completed_at?->toISOString(), 'notes' => $item->notes]]);
    }

    public function uploadAttachment(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:12288'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);
        $file = $data['file'];
        $path = $file->store("work-orders/{$workOrder->id}", 'local');
        $attachment = $workOrder->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'kind' => str_starts_with($file->getMimeType() ?: '', 'image/') ? 'photo' : 'document',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'caption' => $data['caption'] ?? null,
        ]);

        return response()->json(['data' => [
            'id' => $attachment->id,
            'kind' => $attachment->kind,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'caption' => $attachment->caption,
        ]], 201);
    }

    public function uploadSignature(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'signed_by_name' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'file', 'mimes:png', 'max:750'],
        ]);
        $binary = $data['signature']->get();
        abort_unless(str_starts_with($binary, "\x89PNG\r\n\x1a\n"), 422, __('Invalid signature data.'));

        if ($workOrder->signature_path) {
            Storage::disk('local')->delete($workOrder->signature_path);
        }
        $path = "work-orders/{$workOrder->id}/signature.png";
        Storage::disk('local')->put($path, $binary);
        $workOrder->update([
            'signed_by_name' => $data['signed_by_name'],
            'signature_path' => $path,
            'signed_at' => now(),
        ]);

        return response()->json(['data' => [
            'signed_by_name' => $workOrder->signed_by_name,
            'signed_at' => $workOrder->signed_at->toISOString(),
        ]], 201);
    }

    public function recordArrival(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'arrived_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $visit = $workOrder->visits()->create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['data' => [
            'id' => $visit->id,
            'latitude' => (float) $visit->latitude,
            'longitude' => (float) $visit->longitude,
            'accuracy_meters' => $visit->accuracy_meters !== null ? (float) $visit->accuracy_meters : null,
            'arrived_at' => $visit->arrived_at->toISOString(),
        ]], 201);
    }

    private function accessible(Request $request): Builder
    {
        return WorkOrder::query()->when($request->user()->hasRole('technician'), fn (Builder $query) => $query->where('assigned_user_id', $request->user()->id));
    }

    private function ensureAccess(Request $request, WorkOrder $workOrder): void
    {
        if ($request->user()->hasRole('technician')) {
            abort_unless($workOrder->assigned_user_id === $request->user()->id, 403);
        }
    }

    /** @return array<string, mixed> */
    private function summary(WorkOrder $order): array
    {
        return ['id' => $order->id, 'number' => $order->number, 'title' => $order->title, 'status' => $order->status,
            'scheduled_start' => $order->scheduled_start?->toISOString(), 'scheduled_end' => $order->scheduled_end?->toISOString(),
            'updated_at' => $order->updated_at->toISOString(), 'assigned_user' => $order->assignedUser?->only(['id', 'name']),
            'signed_by_name' => $order->signed_by_name, 'signed_at' => $order->signed_at?->toISOString(),
            'installation' => $order->installation ? ['id' => $order->installation->id, 'project_reference' => $order->installation->project_reference,
                'site_name' => $order->installation->site_name, 'address' => $order->installation->site_address_line_1,
                'city' => $order->installation->site_city, 'postal_code' => $order->installation->site_postal_code,
                'latitude' => $order->installation->site_latitude !== null ? (float) $order->installation->site_latitude : null,
                'longitude' => $order->installation->site_longitude !== null ? (float) $order->installation->site_longitude : null] : null,
            'checklist' => $order->relationLoaded('checklistItems') ? $order->checklistItems->map(fn ($item) => [
                'id' => $item->id, 'position' => $item->position, 'label' => $item->label, 'is_required' => $item->is_required,
                'completed_at' => $item->completed_at?->toISOString(), 'notes' => $item->notes,
            ])->values() : [],
        ];
    }
}
