<?php

namespace App\Jobs;

use App\Models\MobileDevice;
use App\Services\Push\ApnsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWorkOrderPush implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public int $workOrderId,
        public string $title,
        public string $body,
    ) {}

    public function handle(ApnsClient $apns): void
    {
        MobileDevice::where('user_id', $this->userId)->where('enabled', true)->eachById(
            fn (MobileDevice $device) => $apns->send($device, [
                'aps' => ['alert' => ['title' => $this->title, 'body' => $this->body], 'sound' => 'default'],
                'work_order_id' => $this->workOrderId,
            ]),
        );
    }
}
