<?php

namespace App\Notifications;

use App\Models\HrLeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly HrLeaveRequest $leave) {}

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
        $name = trim($this->leave->person->first_name.' '.($this->leave->person->last_name ?? ''));

        return [
            'message' => __('New leave request from :name.', ['name' => $name]),
            'url' => route('human-resources.leave.index'),
        ];
    }
}
