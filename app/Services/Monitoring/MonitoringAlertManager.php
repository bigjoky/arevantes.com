<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringAlert;
use App\Models\MonitoringReading;
use App\Models\User;
use App\Notifications\MonitoringIncidentNotification;

class MonitoringAlertManager
{
    public function record(MonitoringReading $reading): void
    {
        $active = MonitoringAlert::query()
            ->where('installation_id', $reading->installation_id)
            ->where('provider', $reading->provider)
            ->whereNull('resolved_at')
            ->latest('started_at')
            ->first();

        if ($reading->status === 'healthy') {
            $active?->update(['resolved_at' => $reading->recorded_at, 'last_seen_at' => $reading->recorded_at]);

            return;
        }

        if ($active && $active->status !== $reading->status) {
            $active->update(['resolved_at' => $reading->recorded_at]);
            $active = null;
        }

        $alert = $active ?? MonitoringAlert::create([
            'installation_id' => $reading->installation_id,
            'provider' => $reading->provider,
            'status' => $reading->status ?? 'unknown',
            'message' => $reading->status_message ?? 'Monitoring incident detected.',
            'started_at' => $reading->recorded_at,
            'last_seen_at' => $reading->recorded_at,
        ]);

        if ($active) {
            $alert->update([
                'message' => $reading->status_message ?? $alert->message,
                'occurrences' => $alert->occurrences + 1,
                'last_seen_at' => $reading->recorded_at,
            ]);
        }

        if ($alert->occurrences >= 2 && ! $alert->notified_at) {
            $installation = $reading->installation;
            $recipients = User::query()
                ->where('role', 'admin')
                ->when($installation->technician_user_id, fn ($query, $id) => $query->orWhereKey($id))
                ->get();

            $recipients->each->notify(new MonitoringIncidentNotification($alert));
            $alert->update(['notified_at' => now()]);
        }
    }
}
