<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ActionSummaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $category,
        private readonly int $count,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'count' => $this->count,
            'message' => trans_choice($this->message(), $this->count, ['count' => $this->count]),
            'url' => $this->url,
        ];
    }

    private function message(): string
    {
        return match ($this->category) {
            'operations' => '{1} :count operational action requires attention|[2,*] :count operational actions require attention',
            'collections' => '{1} :count customer invoice is due or overdue|[2,*] :count customer invoices are due or overdue',
            'payments' => '{1} :count supplier payment is due or overdue|[2,*] :count supplier payments are due or overdue',
            'banking' => '{1} :count bank movement is pending reconciliation|[2,*] :count bank movements are pending reconciliation',
            default => '{1} :count action requires attention|[2,*] :count actions require attention',
        };
    }
}
