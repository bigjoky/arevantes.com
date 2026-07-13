<?php

namespace App\Services\Work;

use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TechnicianSchedule
{
    public function assertAvailable(?int $userId, mixed $start, mixed $end, ?int $exceptOrderId = null): void
    {
        if (! $userId || ! $start || ! $end) {
            return;
        }

        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        $conflict = WorkOrder::query()
            ->where('assigned_user_id', $userId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptOrderId, fn ($query) => $query->where('id', '!=', $exceptOrderId))
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_end')
            ->where('scheduled_start', '<', $end)
            ->where('scheduled_end', '>', $start)
            ->first(['id', 'number', 'scheduled_start', 'scheduled_end']);

        if ($conflict) {
            throw ValidationException::withMessages([
                'scheduled_start' => __('This technician is already assigned to :number from :start to :end.', [
                    'number' => $conflict->number,
                    'start' => $conflict->scheduled_start->format('d/m/Y H:i'),
                    'end' => $conflict->scheduled_end->format('d/m/Y H:i'),
                ]),
            ]);
        }
    }

    /** @return array<int, true> */
    public function conflictIds(Collection $orders): array
    {
        $conflicts = [];
        $orders->whereNotIn('status', ['completed', 'cancelled'])
            ->filter(fn (WorkOrder $order) => $order->assigned_user_id && $order->scheduled_start && $order->scheduled_end)
            ->groupBy('assigned_user_id')
            ->each(function (Collection $technicianOrders) use (&$conflicts): void {
                $items = $technicianOrders->sortBy('scheduled_start')->values();
                foreach ($items as $position => $order) {
                    foreach ($items->slice($position + 1) as $candidate) {
                        if ($candidate->scheduled_start >= $order->scheduled_end) {
                            break;
                        }
                        if ($candidate->scheduled_end > $order->scheduled_start) {
                            $conflicts[$order->id] = true;
                            $conflicts[$candidate->id] = true;
                        }
                    }
                }
            });

        return $conflicts;
    }
}
