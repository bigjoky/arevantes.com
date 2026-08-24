<?php

namespace App\Services\Work;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Validation\ValidationException;

class TechnicianCandidateFinder
{
    public function __construct(private TechnicianSchedule $schedule) {}

    /** @return array<int, array{id:int,name:string}> */
    public function qualifiedFor(WorkOrder $order): array
    {
        return User::query()->whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name'])
            ->filter(function (User $user) use ($order): bool {
                try {
                    $this->schedule->assertAvailable(
                        $user->id,
                        $order->scheduled_start,
                        $order->scheduled_end,
                        $order->id,
                        $order->required_skills ?? [],
                        $order->service_zone,
                    );

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            })->map(fn (User $user): array => $user->only(['id', 'name']))->values()->all();
    }
}
