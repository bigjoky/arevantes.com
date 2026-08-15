<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\SecurityAlertNotification;

class SecurityEventRecorder
{
    public function record(string $type, ?User $user, array $context = [], string $severity = 'info', bool $alertAdmins = false): SecurityEvent
    {
        $event = SecurityEvent::create([
            'user_id' => $user?->id,
            'type' => $type,
            'severity' => $severity,
            'correlation_id' => request()?->attributes->get('correlation_id'),
            'ip_address' => request()?->ip(),
            'context' => $context,
        ]);
        if ($alertAdmins) {
            User::query()->with(['permissionProfile.permissions', 'permissionOverrides'])->get()
                ->filter(fn (User $recipient) => app(PermissionResolver::class)->allows($recipient, 'manage-admin'))
                ->each->notify(
                    new SecurityAlertNotification(__('Security alert'), __(str_replace('_', ' ', ucfirst($type))), ['security_event_id' => $event->id])
                );
        }

        return $event;
    }
}
