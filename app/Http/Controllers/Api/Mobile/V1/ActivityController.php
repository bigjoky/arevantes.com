<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Support\Time\BusinessTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $activities = CrmActivity::query()
            ->with('installation:id,project_reference,site_name,site_address_line_1,site_city,site_postal_code,site_latitude,site_longitude')
            ->where('assigned_user_id', $request->user()->id)
            ->whereNotNull('installation_id')
            ->whereNull('work_order_id')
            ->whereIn('status', ['pending', 'in_progress', 'blocked'])
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->paginate($validated['per_page'] ?? 100);

        return response()->json([
            'data' => collect($activities->items())->map(fn (CrmActivity $activity) => $this->summary($activity)),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    public function show(Request $request, CrmActivity $activity): JsonResponse
    {
        $this->ensureAccess($request, $activity);
        abort_if($activity->work_order_id !== null || $activity->installation_id === null, 404);
        $activity->load('installation:id,project_reference,site_name,site_address_line_1,site_city,site_postal_code,site_latitude,site_longitude');

        return response()->json(['data' => [...$this->summary($activity), 'notes' => $activity->notes]]);
    }

    public function changeStatus(Request $request, CrmActivity $activity, OpportunitySalesCycle $salesCycle, LeadResponseSla $responseSla): JsonResponse
    {
        $this->ensureAccess($request, $activity);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'blocked', 'completed'])],
            'blocked_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,blocked'],
        ]);
        $activity = DB::transaction(function () use ($request, $activity, $data, $salesCycle, $responseSla): CrmActivity {
            $locked = CrmActivity::query()->with('opportunity')->lockForUpdate()->findOrFail($activity->id);
            $this->ensureAccess($request, $locked);
            abort_if($locked->work_order_id !== null || $locked->installation_id === null || in_array($locked->status, ['completed', 'cancelled'], true), 422);
            $locked->update([
                'status' => $data['status'],
                'blocked_reason' => $data['status'] === 'blocked' ? $data['blocked_reason'] : null,
                'completed_at' => $data['status'] === 'completed' ? BusinessTime::now() : null,
            ]);
            $responseSla->record($locked);
            if ($locked->opportunity) {
                $salesCycle->synchronizeStage($locked->opportunity, $request->user());
                $salesCycle->refreshScore($locked->opportunity);
            }

            return $locked;
        });
        $activity->load('installation:id,project_reference,site_name,site_address_line_1,site_city,site_postal_code,site_latitude,site_longitude');

        return response()->json(['data' => $this->summary($activity)]);
    }

    private function ensureAccess(Request $request, CrmActivity $activity): void
    {
        abort_unless($activity->assigned_user_id === $request->user()->id, 403);
    }

    /** @return array<string, mixed> */
    private function summary(CrmActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'title' => $activity->title,
            'priority' => $activity->priority,
            'status' => $activity->status,
            'blocked_reason' => $activity->blocked_reason,
            'starts_at' => $activity->starts_at?->toISOString(),
            'due_at' => $activity->due_at?->toISOString(),
            'updated_at' => $activity->updated_at->toISOString(),
            'installation' => $activity->installation ? [
                'id' => $activity->installation->id,
                'project_reference' => $activity->installation->project_reference,
                'site_name' => $activity->installation->site_name,
                'address' => $activity->installation->site_address_line_1,
                'city' => $activity->installation->site_city,
                'postal_code' => $activity->installation->site_postal_code,
                'latitude' => $activity->installation->site_latitude !== null ? (float) $activity->installation->site_latitude : null,
                'longitude' => $activity->installation->site_longitude !== null ? (float) $activity->installation->site_longitude : null,
            ] : null,
        ];
    }
}
