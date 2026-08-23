<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\CommercialRoutePlan;
use App\Models\CommercialRouteStop;
use App\Services\Crm\CommercialRoutePlanner;
use App\Support\Crm\CommercialRoutePayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialRouteController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = CarbonImmutable::parse($data['date'] ?? today());
        $plan = CommercialRoutePlan::query()
            ->with('stops.customer:id,first_name,last_name,company_name,phone,email,address_line_1,city,postal_code,preferred_contact_channel,do_not_contact')
            ->where('user_id', $request->user()->id)
            ->whereDate('route_date', $date)
            ->first();

        return response()->json(['data' => CommercialRoutePayload::make($plan)]);
    }

    public function store(Request $request, CommercialRoutePlanner $planner): JsonResponse
    {
        $data = $request->validate([
            'route_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(30)->toDateString()],
            'origin_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:origin_longitude'],
            'origin_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:origin_latitude'],
        ]);
        $plan = $planner->generate(
            $request->user(),
            CarbonImmutable::parse($data['route_date']),
            isset($data['origin_latitude']) ? (float) $data['origin_latitude'] : null,
            isset($data['origin_longitude']) ? (float) $data['origin_longitude'] : null,
        );

        return response()->json(['data' => CommercialRoutePayload::make($plan)], 201);
    }

    public function arrive(Request $request, CommercialRoutePlan $routePlan, CommercialRouteStop $routeStop): JsonResponse
    {
        $this->ensureOwnership($request, $routePlan, $routeStop);
        $data = $request->validate([
            'arrived_at' => ['required', 'date', 'before_or_equal:now'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($routePlan, $routeStop, $data): void {
            $lockedPlan = CommercialRoutePlan::query()->whereKey($routePlan)->lockForUpdate()->firstOrFail();
            $lockedStop = CommercialRouteStop::query()->whereKey($routeStop)->lockForUpdate()->firstOrFail();
            if ($lockedStop->status !== 'planned') {
                throw ValidationException::withMessages(['stop' => __('Only a planned stop can register an arrival.')]);
            }
            $lockedStop->update([
                'status' => 'arrived',
                'arrived_at' => $data['arrived_at'],
                'arrival_latitude' => $data['latitude'],
                'arrival_longitude' => $data['longitude'],
                'arrival_accuracy_meters' => $data['accuracy_meters'] ?? null,
                'address' => $lockedStop->address ?: ($data['address'] ?? null),
            ]);
            if ($lockedPlan->status === 'planned') {
                $lockedPlan->update(['status' => 'active', 'started_at' => $data['arrived_at']]);
            }
            if ($lockedStop->activity && $lockedStop->activity->status === 'pending') {
                $lockedStop->activity->update(['status' => 'in_progress']);
            }
        });

        return $this->response($routePlan);
    }

    public function complete(Request $request, CommercialRoutePlan $routePlan, CommercialRouteStop $routeStop): JsonResponse
    {
        $this->ensureOwnership($request, $routePlan, $routeStop);
        $data = $request->validate([
            'completed_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($routePlan, $routeStop, $data): void {
            $lockedPlan = CommercialRoutePlan::query()->whereKey($routePlan)->lockForUpdate()->firstOrFail();
            $lockedStop = CommercialRouteStop::query()->whereKey($routeStop)->lockForUpdate()->firstOrFail();
            if ($lockedStop->status !== 'arrived') {
                throw ValidationException::withMessages(['stop' => __('Register the arrival before completing the stop.')]);
            }
            $lockedStop->update([
                'status' => 'completed',
                'completed_at' => $data['completed_at'],
                'notes' => $data['notes'] ?? $lockedStop->notes,
            ]);
            $lockedStop->activity?->update(['status' => 'completed', 'completed_at' => $data['completed_at']]);

            if (! $lockedPlan->stops()->where('status', '!=', 'completed')->exists()) {
                $lockedPlan->update(['status' => 'completed', 'completed_at' => $data['completed_at']]);
            } elseif ($lockedPlan->status === 'planned') {
                $lockedPlan->update(['status' => 'active', 'started_at' => $data['completed_at']]);
            }
        });

        return $this->response($routePlan);
    }

    private function ensureOwnership(Request $request, CommercialRoutePlan $routePlan, CommercialRouteStop $routeStop): void
    {
        abort_unless(
            $routePlan->user_id === $request->user()->id && $routeStop->commercial_route_plan_id === $routePlan->id,
            404,
        );
    }

    private function response(CommercialRoutePlan $routePlan): JsonResponse
    {
        return response()->json(['data' => CommercialRoutePayload::make(
            $routePlan->fresh('stops.customer:id,first_name,last_name,company_name,phone,email,address_line_1,city,postal_code,preferred_contact_channel,do_not_contact'),
        )]);
    }
}
