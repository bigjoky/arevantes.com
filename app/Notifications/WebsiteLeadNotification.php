<?php

namespace App\Notifications;

use App\Models\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WebsiteLeadNotification extends Notification
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
            'category' => 'commercial',
            'opportunity_id' => $this->opportunity->id,
            'message' => 'Nueva solicitud recibida desde arevantes.com: '.$this->opportunity->title,
            'url' => route('opportunities.index'),
        ];
    }
}
