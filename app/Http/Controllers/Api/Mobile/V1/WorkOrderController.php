<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\StockReservation;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use App\Rules\SafeUpload;
use App\Services\Inventory\WorkOrderMaterialConsumer;
use App\Services\Work\WorkOrderEvidenceRequirements;
use App\Services\Work\WorkOrderStateManager;
use App\Services\Work\WorkOrderWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updated_after' => ['nullable', 'date'],
            'status' => ['nullable', 'in:planned,in_progress,blocked,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sync' => ['nullable', 'boolean'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'snapshot_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);
        $updatedAfter = isset($validated['updated_after'])
            ? CarbonImmutable::parse($validated['updated_after'])->setTimezone(config('app.timezone'))
            : null;
        $query = $this->accessible($request)
            ->with(['installation:id,project_reference,service_type,site_name,site_address_line_1,site_city,site_postal_code,site_latitude,site_longitude', 'assignedUser:id,name', 'checklistItems', 'attachments:id,work_order_id,evidence_key', 'activities:id,work_order_id,blocked_reason'])
            ->when($updatedAfter, fn (Builder $query, CarbonImmutable $date) => $query->where('updated_at', '>', $date))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
        $perPage = $validated['per_page'] ?? 30;

        if ($validated['sync'] ?? false) {
            $snapshotAt = isset($validated['snapshot_at'])
                ? CarbonImmutable::parse($validated['snapshot_at'])->setTimezone(config('app.timezone'))
                : now();
            $orders = $query
                ->where('updated_at', '<=', $snapshotAt)
                ->orderBy('updated_at')
                ->orderBy('id')
                ->cursorPaginate($perPage);
            $orders->appends([
                'sync' => 1,
                'per_page' => $perPage,
                'updated_after' => $validated['updated_after'] ?? null,
                'status' => $validated['status'] ?? null,
                'snapshot_at' => $snapshotAt->toISOString(),
            ]);

            return response()->json([
                'data' => collect($orders->items())->map(fn (WorkOrder $order) => $this->summary($order)),
                'meta' => [
                    'per_page' => $orders->perPage(),
                    'next_cursor' => $orders->nextCursor()?->encode(),
                    'next_page_url' => $orders->nextPageUrl(),
                    'snapshot_at' => $snapshotAt->toISOString(),
                ],
                'running_time_entry' => $this->runningTimeEntry($request),
            ]);
        }

        $orders = $query
            ->orderByRaw("case when status = 'completed' then 1 else 0 end")
            ->orderBy('scheduled_start')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($orders->items())->map(fn (WorkOrder $order) => $this->summary($order)),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'per_page' => $orders->perPage(), 'total' => $orders->total()],
            'running_time_entry' => $this->runningTimeEntry($request),
        ]);
    }

    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $workOrder->load(['installation', 'assignedUser:id,name', 'timeEntries.user:id,name', 'checklistItems', 'attachments', 'stockReservations.product:id,sku,name,track_serials', 'stockReservations.warehouse:id,code,name', 'activities:id,work_order_id,blocked_reason']);

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
                'id' => $entry->id, 'client_uuid' => $entry->client_uuid, 'started_at' => $entry->started_at->toISOString(), 'ended_at' => $entry->ended_at?->toISOString(),
                'duration_minutes' => $entry->duration_minutes, 'travel_km' => (float) $entry->travel_km, 'notes' => $entry->notes,
            ]),
            'attachments' => $workOrder->attachments->map(fn ($attachment) => [
                'id' => $attachment->id, 'kind' => $attachment->kind, 'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type, 'size' => $attachment->size, 'caption' => $attachment->caption,
                'evidence_key' => $attachment->evidence_key,
            ]),
            'materials' => $workOrder->stockReservations->map(fn (StockReservation $reservation) => $this->material($reservation)),
        ]]);
    }

    public function startTimer(Request $request, WorkOrder $workOrder, WorkOrderStateManager $states): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'client_uuid' => ['nullable', 'uuid', Rule::unique('time_entries', 'client_uuid')],
            'started_at' => ['nullable', 'date', 'before_or_equal:now', 'after_or_equal:'.now()->subDays(7)->toISOString()],
            'expected_updated_at' => ['nullable', 'date'],
        ]);
        $startedAt = isset($data['started_at']) ? CarbonImmutable::parse($data['started_at'])->setTimezone(config('app.timezone')) : now();
        $entry = DB::transaction(function () use ($request, $workOrder, $states, $data, $startedAt): TimeEntry {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $this->ensureAccess($request, $locked);
            abort_if(TimeEntry::where('user_id', $request->user()->id)->whereNull('ended_at')->exists(), 422, __('You already have a running timer.'));
            $states->transition($locked, 'in_progress', expectedUpdatedAt: $data['expected_updated_at'] ?? null);
            $entry = TimeEntry::create(['client_uuid' => $data['client_uuid'] ?? null, 'work_order_id' => $locked->id, 'user_id' => $request->user()->id, 'created_by' => $request->user()->id, 'started_at' => $startedAt, 'hourly_cost' => config('work.default_hourly_cost'), 'mileage_rate' => config('work.mileage_rate')]);

            return $entry;
        });

        return response()->json(['data' => ['id' => $entry->id, 'client_uuid' => $entry->client_uuid, 'started_at' => $entry->started_at->toISOString()]], 201);
    }

    public function complete(Request $request, WorkOrder $workOrder, WorkOrderStateManager $states): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'completion_notes' => ['required', 'string', 'max:5000'],
            'expected_updated_at' => ['nullable', 'date'],
        ]);
        $updated = $states->transition($workOrder, 'completed', $data['completion_notes'], expectedUpdatedAt: $data['expected_updated_at'] ?? null);
        $updated->load(['installation', 'assignedUser:id,name', 'checklistItems']);

        return response()->json(['data' => [
            ...$this->summary($updated),
            'completion_notes' => $updated->completion_notes,
            'completed_at' => $updated->completed_at?->toISOString(),
        ]]);
    }

    public function changeStatus(Request $request, WorkOrder $workOrder, WorkOrderStateManager $states): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'status' => ['required', Rule::in(['blocked', 'planned', 'in_progress'])],
            'blocked_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,blocked'],
            'expected_updated_at' => ['nullable', 'date'],
        ]);
        if ($data['status'] === 'blocked') {
            abort_if(TimeEntry::where('work_order_id', $workOrder->id)->where('user_id', $request->user()->id)->whereNull('ended_at')->exists(), 422, __('Stop the running timer before blocking the work order.'));
        }
        $updated = $states->transition(
            $workOrder,
            $data['status'],
            blockedReason: $data['blocked_reason'] ?? null,
            expectedUpdatedAt: $data['expected_updated_at'] ?? null,
        );
        $updated->load(['installation', 'assignedUser:id,name', 'checklistItems', 'activities:id,work_order_id,blocked_reason']);

        return response()->json(['data' => $this->summary($updated)]);
    }

    public function consumeMaterial(Request $request, WorkOrder $workOrder, StockReservation $reservation, WorkOrderMaterialConsumer $consumer): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($reservation->work_order_id === $workOrder->id, 404);
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'serials' => ['nullable', 'string', 'max:10000'],
        ]);
        $updated = $consumer->consume($workOrder, $reservation, (float) $data['quantity'], $data['serials'] ?? null, $request->user()->id);

        return response()->json(['data' => $this->material($updated)]);
    }

    public function stopTimer(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        return $this->stop($request, $timeEntry);
    }

    public function stopTimerByClient(Request $request, string $clientUuid): JsonResponse
    {
        $timeEntry = TimeEntry::where('user_id', $request->user()->id)->where('client_uuid', $clientUuid)->firstOrFail();

        return $this->stop($request, $timeEntry);
    }

    private function stop(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        abort_unless($timeEntry->user_id === $request->user()->id && ! $timeEntry->ended_at, 403);
        $data = $request->validate([
            'ended_at' => ['nullable', 'date', 'before_or_equal:now', 'after_or_equal:'.$timeEntry->started_at->toISOString()],
            'travel_km' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $endedAt = isset($data['ended_at']) ? CarbonImmutable::parse($data['ended_at'])->setTimezone(config('app.timezone')) : now();
        $timeEntry->update([...$data, 'ended_at' => $endedAt, 'duration_minutes' => max(1, (int) round($timeEntry->started_at->diffInMinutes($endedAt)))]);

        return response()->json(['data' => ['id' => $timeEntry->id, 'client_uuid' => $timeEntry->client_uuid, 'ended_at' => $endedAt->toISOString(), 'duration_minutes' => $timeEntry->duration_minutes]]);
    }

    public function updateChecklist(Request $request, WorkOrder $workOrder, WorkOrderChecklistItem $item): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        abort_unless($item->work_order_id === $workOrder->id, 404);
        $data = $request->validate(['completed' => ['required', 'boolean'], 'notes' => ['nullable', 'string']]);
        $item->update(['completed_at' => $data['completed'] ? now() : null, 'completed_by' => $data['completed'] ? $request->user()->id : null, 'notes' => $data['notes'] ?? $item->notes]);

        return response()->json(['data' => ['id' => $item->id, 'completed_at' => $item->completed_at?->toISOString(), 'notes' => $item->notes]]);
    }

    public function uploadAttachment(Request $request, WorkOrder $workOrder, WorkOrderEvidenceRequirements $evidence): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'file' => ['required', 'file', new SafeUpload(['jpg', 'jpeg', 'png', 'webp', 'pdf'], 12288)],
            'caption' => ['nullable', 'string', 'max:500'],
            'evidence_key' => ['nullable', 'string', Rule::in($evidence->allowedKeys($workOrder))],
        ]);
        $file = $data['file'];
        $path = $file->store("work-orders/{$workOrder->id}", config('filesystems.private'));
        $attachment = $workOrder->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'kind' => str_starts_with($file->getMimeType() ?: '', 'image/') ? 'photo' : 'document',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'caption' => $data['caption'] ?? null,
            'evidence_key' => $data['evidence_key'] ?? null,
        ]);

        return response()->json(['data' => [
            'id' => $attachment->id,
            'kind' => $attachment->kind,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'caption' => $attachment->caption,
            'evidence_key' => $attachment->evidence_key,
        ]], 201);
    }

    public function uploadSignature(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureAccess($request, $workOrder);
        $data = $request->validate([
            'signed_by_name' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'file', new SafeUpload(['png'], 750)],
        ]);
        $binary = $data['signature']->get();
        abort_unless(str_starts_with($binary, "\x89PNG\r\n\x1a\n"), 422, __('Invalid signature data.'));

        if ($workOrder->signature_path) {
            Storage::disk(config('filesystems.private'))->delete($workOrder->signature_path);
        }
        $path = "work-orders/{$workOrder->id}/signature.png";
        Storage::disk(config('filesystems.private'))->put($path, $binary);
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

    /** @return array<string, mixed>|null */
    private function runningTimeEntry(Request $request): ?array
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('ended_at')
            ->first();

        return $entry ? [
            'id' => $entry->id,
            'client_uuid' => $entry->client_uuid,
            'started_at' => $entry->started_at->toISOString(),
            'work_order_id' => $entry->work_order_id,
        ] : null;
    }

    /** @return array<string, mixed> */
    private function summary(WorkOrder $order): array
    {
        $evidence = app(WorkOrderEvidenceRequirements::class);

        return ['id' => $order->id, 'number' => $order->number, 'title' => $order->title, 'status' => $order->status,
            'allowed_transitions' => ($order->allowed_transitions_snapshot ?: WorkOrderWorkflow::DEFAULT_TRANSITIONS)[$order->status] ?? [],
            'scheduled_start' => $order->scheduled_start?->toISOString(), 'scheduled_end' => $order->scheduled_end?->toISOString(),
            'updated_at' => $order->updated_at->toISOString(), 'assigned_user' => $order->assignedUser?->only(['id', 'name']),
            'signed_by_name' => $order->signed_by_name, 'signed_at' => $order->signed_at?->toISOString(),
            'required_evidence' => $order->required_evidence ?? [], 'evidence_progress' => $evidence->progress($order),
            'handover_documents' => $order->handover_documents ?? [],
            'blocked_reason' => $order->relationLoaded('activities') ? $order->activities->first()?->blocked_reason : null,
            'installation' => $order->installation ? ['id' => $order->installation->id, 'project_reference' => $order->installation->project_reference,
                'site_name' => $order->installation->site_name, 'address' => $order->installation->site_address_line_1,
                'service_type' => $order->installation->service_type,
                'city' => $order->installation->site_city, 'postal_code' => $order->installation->site_postal_code,
                'latitude' => $order->installation->site_latitude !== null ? (float) $order->installation->site_latitude : null,
                'longitude' => $order->installation->site_longitude !== null ? (float) $order->installation->site_longitude : null] : null,
            'checklist' => $order->relationLoaded('checklistItems') ? $order->checklistItems->map(fn ($item) => [
                'id' => $item->id, 'position' => $item->position, 'label' => $item->label, 'is_required' => $item->is_required,
                'completed_at' => $item->completed_at?->toISOString(), 'notes' => $item->notes,
            ])->values() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function material(StockReservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'status' => $reservation->status,
            'quantity' => (float) $reservation->quantity,
            'consumed_quantity' => (float) $reservation->consumed_quantity,
            'remaining_quantity' => max(0, round((float) $reservation->quantity - (float) $reservation->consumed_quantity, 3)),
            'product' => $reservation->product?->only(['id', 'sku', 'name', 'track_serials']),
            'warehouse' => $reservation->warehouse?->only(['id', 'code', 'name']),
        ];
    }
}
