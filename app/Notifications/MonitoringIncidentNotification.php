<?php

namespace App\Notifications;

use App\Models\MonitoringAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MonitoringIncidentNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly MonitoringAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'installation_id' => $this->alert->installation_id,
            'provider' => $this->alert->provider,
            'status' => $this->alert->status,
            'message' => $this->alert->message,
            'url' => route('installations.show', $this->alert->installation_id),
        ];
    }
}
