<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnalyticsReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $areaName,
        private readonly string $format,
        private readonly string $filename,
        private readonly string $contents,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Informe de analítica disponible'),
            'message' => __('Se ha generado el informe programado de :area.', ['area' => $this->areaName]),
            'kind' => 'analytics_report',
            'area' => $this->areaName,
            'format' => $this->format,
            'url' => route('analytics.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mime = $this->format === 'pdf' ? 'application/pdf' : 'application/vnd.ms-excel';

        return (new MailMessage)
            ->subject(__('Informe de analítica · :area', ['area' => $this->areaName]))
            ->line(__('Adjuntamos el informe programado con el alcance autorizado en el momento de generarlo.'))
            ->attachData($this->contents, $this->filename, ['mime' => $mime]);
    }
}
