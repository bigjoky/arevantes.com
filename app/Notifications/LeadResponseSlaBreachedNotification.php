<?php

namespace App\Notifications;

use App\Models\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadResponseSlaBreachedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Opportunity $opportunity) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'opportunity_id' => $this->opportunity->id,
            'code' => $this->opportunity->code,
            'message' => __('Opportunity :code is outside the first-response SLA.', [
                'code' => $this->opportunity->code ?: '#'.$this->opportunity->id,
            ]),
            'url' => route('opportunities.index', ['attention' => 'response_sla']),
        ];
    }
}
