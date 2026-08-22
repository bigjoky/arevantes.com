<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnalyticsThresholdNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $metricName,
        private readonly float $value,
        private readonly string $operator,
        private readonly float $threshold,
        private readonly string $area,
        private readonly string $channel = 'database',
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channel === 'mail' ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Alerta de analítica'),
            'message' => __(':metric ha alcanzado :value (:operator :threshold).', [
                'metric' => $this->metricName, 'value' => $this->value,
                'operator' => $this->operator, 'threshold' => $this->threshold,
            ]),
            'kind' => 'analytics',
            'area' => $this->area,
            'url' => route('analytics.index', ['area' => $this->area]),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'].' · '.$this->metricName)
            ->line($data['message'])
            ->action(__('Abrir analítica'), $data['url']);
    }
}
