<?php

namespace App\Services\AfterSales;

use App\Models\ServiceSlaPolicy;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\ServiceSlaEscalationNotification;
use Illuminate\Support\Facades\DB;

class SlaManager
{
    public function apply(ServiceTicket $ticket, ?string $customerType = null): void
    {
        $customerType ??= $ticket->loadMissing('installation.customer')->installation?->customer?->customer_type;
        $policy = ServiceSlaPolicy::query()->where('is_active', true)->where('priority', $ticket->priority)
            ->where(fn ($query) => $query->where('customer_type', $customerType)->orWhereNull('customer_type'))
            ->orderByRaw('customer_type is null')->first();
        if (! $policy) {
            $defaults = ['urgent' => [30, 240, 60], 'high' => [120, 480, 180], 'normal' => [480, 2880, 720], 'low' => [1440, 7200, 2880]];
            [$response, $resolution] = $defaults[$ticket->priority] ?? $defaults['normal'];
            $ticket->update(['first_response_due_at' => now()->addMinutes($response), 'resolution_due_at' => now()->addMinutes($resolution)]);

            return;
        }
        $ticket->update(['sla_policy_id' => $policy->id, 'first_response_due_at' => now()->addMinutes($policy->first_response_minutes), 'resolution_due_at' => now()->addMinutes($policy->resolution_minutes)]);
    }

    public function escalateOverdue(): int
    {
        $count = 0;
        ServiceTicket::query()->whereNotIn('status', ['resolved', 'closed'])->where('escalation_level', '<', 3)->where(function ($query): void {
            $query->where(fn ($query) => $query->whereNull('first_responded_at')->where('first_response_due_at', '<', now()))
                ->orWhere('resolution_due_at', '<', now());
        })->each(function (ServiceTicket $ticket) use (&$count): void {
            DB::transaction(function () use ($ticket, &$count): void {
                $locked = ServiceTicket::query()->with('slaPolicy')->lockForUpdate()->findOrFail($ticket->id);
                if (in_array($locked->status, ['resolved', 'closed'], true) || $locked->escalation_level >= 3 || ! $this->isBreached($locked)) {
                    return;
                }
                $interval = $locked->slaPolicy?->escalation_minutes ?? $this->defaultEscalationMinutes($locked->priority);
                if ($locked->escalated_at?->greaterThan(now()->subMinutes($interval))) {
                    return;
                }

                $locked->update(['escalated_at' => now(), 'escalation_level' => $locked->escalation_level + 1]);
                $recipients = User::query()
                    ->where('role', 'admin')
                    ->when($locked->assigned_user_id, fn ($query, $id) => $query->orWhere('id', $id))
                    ->get();
                $recipients->each->notify(new ServiceSlaEscalationNotification($locked));
                $count++;
            });
        });

        return $count;
    }

    private function isBreached(ServiceTicket $ticket): bool
    {
        return (! $ticket->first_responded_at && $ticket->first_response_due_at?->isPast())
            || $ticket->resolution_due_at?->isPast();
    }

    private function defaultEscalationMinutes(string $priority): int
    {
        return match ($priority) {
            'urgent' => 60,
            'high' => 180,
            'low' => 2880,
            default => 720,
        };
    }
}
