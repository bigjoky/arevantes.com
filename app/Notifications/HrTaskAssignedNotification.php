<?php

namespace App\Notifications;

use App\Models\HrEmployeeTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HrTaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly HrEmployeeTask $task) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('New HR task: :title', ['title' => $this->task->title]),
            'url' => route('my-human-resources.index'),
        ];
    }
}
