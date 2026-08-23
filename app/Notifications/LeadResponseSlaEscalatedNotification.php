<?php

namespace App\Notifications;

use App\Models\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadResponseSlaEscalatedNotification extends Notification
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
            'owner_user_id' => $this->opportunity->owner_user_id,
            'code' => $this->opportunity->code,
            'message' => __('Opportunity :code remains unanswered after its SLA warning.', [
                'code' => $this->opportunity->code ?: '#'.$this->opportunity->id,
            ]),
            'url' => route('opportunities.index', ['attention' => 'response_sla']),
        ];
    }
}
