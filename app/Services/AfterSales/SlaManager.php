<?php

namespace App\Services\AfterSales;

use App\Models\ServiceSlaPolicy;
use App\Models\ServiceTicket;

class SlaManager
{
    public function apply(ServiceTicket $ticket, ?string $customerType = null): void
    {
        $policy = ServiceSlaPolicy::query()->where('is_active', true)->where('priority', $ticket->priority)
            ->where(fn ($query) => $query->where('customer_type', $customerType)->orWhereNull('customer_type'))
            ->orderByRaw('customer_type is null')->first();
        if (! $policy) {
            $defaults = ['urgent' => [30, 240, 60], 'high' => [120, 480, 180], 'normal' => [480, 2880, 720], 'low' => [1440, 7200, 2880]];
            [$response, $resolution, $escalation] = $defaults[$ticket->priority] ?? $defaults['normal'];
            $ticket->update(['first_response_due_at' => now()->addMinutes($response), 'resolution_due_at' => now()->addMinutes($resolution)]);

            return;
        }
        $ticket->update(['sla_policy_id' => $policy->id, 'first_response_due_at' => now()->addMinutes($policy->first_response_minutes), 'resolution_due_at' => now()->addMinutes($policy->resolution_minutes)]);
    }

    public function escalateOverdue(): int
    {
        $count = 0;
        ServiceTicket::query()->whereNotIn('status', ['resolved', 'closed'])->where(function ($query): void {
            $query->where(fn ($query) => $query->whereNull('first_responded_at')->where('first_response_due_at', '<', now()))
                ->orWhere('resolution_due_at', '<', now());
        })->each(function (ServiceTicket $ticket) use (&$count): void {
            $ticket->update(['escalated_at' => now(), 'escalation_level' => min(255, $ticket->escalation_level + 1)]);
            $count++;
        });

        return $count;
    }
}
