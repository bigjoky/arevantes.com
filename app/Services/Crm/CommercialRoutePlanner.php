<?php

namespace App\Services\Crm;

use App\Models\CommercialRoutePlan;
use App\Models\CommercialVisit;
use App\Models\CrmActivity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialRoutePlanner
{
    public function generate(User $commercial, CarbonInterface $date, ?float $originLatitude, ?float $originLongitude): CommercialRoutePlan
    {
        return DB::transaction(function () use ($commercial, $date, $originLatitude, $originLongitude): CommercialRoutePlan {
            $plan = CommercialRoutePlan::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $commercial->id, 'route_date' => $date->toDateString()],
                ['status' => 'planned'],
            );

            if ($plan->status !== 'planned') {
                throw ValidationException::withMessages(['route' => __('An active or completed route cannot be regenerated.')]);
            }

            $activities = CrmActivity::query()
                ->with('person:id,first_name,last_name,company_name,address_line_1,city,postal_code')
                ->where('assigned_user_id', $commercial->id)
                ->whereIn('type', ['visit', 'meeting'])
                ->whereIn('status', ['pending', 'in_progress'])
                ->where(function ($query) use ($date): void {
                    $query->whereDate('starts_at', $date->toDateString())
                        ->orWhere(function ($query) use ($date): void {
                            $query->whereNull('starts_at')->whereDate('due_at', $date->toDateString());
                        });
                })
                ->orderByRaw('case when starts_at is null then 1 else 0 end')
                ->orderBy('starts_at')
                ->orderBy('due_at')
                ->get();

            $lastVisits = CommercialVisit::query()
                ->whereNull('prospect_type') // Field GPS has short retention and must not be copied into route stops.
                ->where('user_id', $commercial->id)
                ->whereIn('person_id', $activities->pluck('person_id')->filter()->unique())
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest('visited_at')
                ->get()
                ->unique('person_id')
                ->keyBy('person_id');

            $candidates = $activities->map(function (CrmActivity $activity) use ($lastVisits): array {
                $customer = $activity->person;
                $visit = $customer ? $lastVisits->get($customer->id) : null;
                $address = $customer ? collect([
                    $customer->address_line_1,
                    trim(implode(' ', array_filter([$customer->postal_code, $customer->city]))),
                ])->filter()->implode(', ') : null;

                return [
                    'activity' => $activity,
                    'person_id' => $customer?->id,
                    'title' => $activity->title,
                    'address' => $address ?: $visit?->address,
                    'latitude' => $visit?->latitude,
                    'longitude' => $visit?->longitude,
                    'planned_at' => $activity->starts_at ?? $activity->due_at,
                ];
            });

            $ordered = $this->orderStops($candidates, $originLatitude, $originLongitude);

            $plan->stops()->delete();
            foreach ($ordered->values() as $index => $candidate) {
                $plan->stops()->create([
                    'crm_activity_id' => $candidate['activity']->id,
                    'person_id' => $candidate['person_id'],
                    'position' => $index + 1,
                    'status' => 'planned',
                    'title' => $candidate['title'],
                    'address' => $candidate['address'],
                    'latitude' => $candidate['latitude'],
                    'longitude' => $candidate['longitude'],
                    'planned_at' => $candidate['planned_at'],
                ]);
            }

            $plan->update([
                'origin_latitude' => $originLatitude,
                'origin_longitude' => $originLongitude,
                'optimized_at' => now(),
            ]);

            return $plan->fresh('stops.customer:id,first_name,last_name,company_name,phone,email,address_line_1,city,postal_code,preferred_contact_channel,do_not_contact');
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return Collection<int, array<string, mixed>>
     */
    private function orderStops(Collection $candidates, ?float $originLatitude, ?float $originLongitude): Collection
    {
        if ($originLatitude === null || $originLongitude === null) {
            return $candidates->sortBy(fn (array $candidate) => $candidate['planned_at']?->getTimestamp() ?? PHP_INT_MAX)->values();
        }

        [$located, $unlocated] = $candidates->partition(
            fn (array $candidate) => $candidate['latitude'] !== null && $candidate['longitude'] !== null,
        );
        $ordered = collect();
        $currentLatitude = $originLatitude;
        $currentLongitude = $originLongitude;

        while ($located->isNotEmpty()) {
            $nextKey = $located->keys()->sortBy(fn ($key) => $this->distance(
                $currentLatitude,
                $currentLongitude,
                (float) $located[$key]['latitude'],
                (float) $located[$key]['longitude'],
            ))->first();
            $next = $located->pull($nextKey);
            $ordered->push($next);
            $currentLatitude = (float) $next['latitude'];
            $currentLongitude = (float) $next['longitude'];
        }

        return $ordered->concat($unlocated->sortBy(fn (array $candidate) => $candidate['planned_at']?->getTimestamp() ?? PHP_INT_MAX))->values();
    }

    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
