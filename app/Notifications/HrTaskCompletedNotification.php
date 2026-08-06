<?php

namespace App\Notifications;

use App\Models\HrEmployeeTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HrTaskCompletedNotification extends Notification
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
        $name = trim($this->task->person->first_name.' '.($this->task->person->last_name ?? ''));

        return [
            'message' => __(':name completed HR task: :title', [
                'name' => $name,
                'title' => $this->task->title,
            ]),
            'url' => route('human-resources.tasks.index'),
        ];
    }
}
