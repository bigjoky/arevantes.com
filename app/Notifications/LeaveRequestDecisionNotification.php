<?php

namespace App\Notifications;

use App\Models\HrLeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestDecisionNotification extends Notification
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
        return [
            'message' => __('Your leave request has been :status.', [
                'status' => $this->leave->status,
            ]),
            'url' => route('my-human-resources.index'),
        ];
    }
}
