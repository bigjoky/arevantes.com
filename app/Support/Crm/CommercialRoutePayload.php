<?php

namespace App\Support\Crm;

use App\Models\CommercialRoutePlan;
use App\Models\CommercialRouteStop;

class CommercialRoutePayload
{
    /** @return array<string, mixed>|null */
    public static function make(?CommercialRoutePlan $plan): ?array
    {
        if (! $plan) {
            return null;
        }

        $plan->loadMissing('stops.customer:id,first_name,last_name,company_name,phone,email,address_line_1,city,postal_code,preferred_contact_channel,do_not_contact');
        $completed = $plan->stops->where('status', 'completed')->count();

        return [
            'id' => $plan->id,
            'route_date' => $plan->route_date->toDateString(),
            'status' => $plan->status,
            'origin' => $plan->origin_latitude !== null && $plan->origin_longitude !== null ? [
                'latitude' => $plan->origin_latitude,
                'longitude' => $plan->origin_longitude,
            ] : null,
            'optimized_at' => $plan->optimized_at?->toISOString(),
            'started_at' => $plan->started_at?->toISOString(),
            'completed_at' => $plan->completed_at?->toISOString(),
            'summary' => [
                'total' => $plan->stops->count(),
                'completed' => $completed,
                'remaining' => $plan->stops->count() - $completed,
            ],
            'stops' => $plan->stops->map(fn (CommercialRouteStop $stop) => [
                'id' => $stop->id,
                'activity_id' => $stop->crm_activity_id,
                'position' => $stop->position,
                'status' => $stop->status,
                'title' => $stop->title,
                'address' => $stop->address,
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude,
                'planned_at' => $stop->planned_at?->toISOString(),
                'arrived_at' => $stop->arrived_at?->toISOString(),
                'completed_at' => $stop->completed_at?->toISOString(),
                'notes' => $stop->notes,
                'customer' => $stop->customer ? [
                    'id' => $stop->customer->id,
                    'name' => $stop->customer->company_name ?: trim($stop->customer->first_name.' '.($stop->customer->last_name ?? '')),
                    'phone' => $stop->customer->phone,
                    'email' => $stop->customer->email,
                    'address' => $stop->customer->address_line_1,
                    'city' => $stop->customer->city,
                    'postal_code' => $stop->customer->postal_code,
                    'preferred_contact_channel' => $stop->customer->preferred_contact_channel,
                    'do_not_contact' => $stop->customer->do_not_contact,
                ] : null,
            ])->values(),
        ];
    }
}
