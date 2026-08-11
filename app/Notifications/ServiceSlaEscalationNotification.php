<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServiceSlaEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ServiceTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'service_ticket_id' => $this->ticket->id,
            'number' => $this->ticket->number,
            'priority' => $this->ticket->priority,
            'escalation_level' => $this->ticket->escalation_level,
            'message' => __('SLA breached for service ticket :number.', ['number' => $this->ticket->number]),
            'url' => route('after-sales.index'),
        ];
    }
}
