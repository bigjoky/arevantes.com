<?php

namespace App\Jobs;

use App\Exceptions\AutomationBlocked;
use App\Models\MobileDevice;
use App\Models\WorkOrder;
use App\Services\Automation\ControlledJob;
use App\Services\Automation\JobSnapshot;
use App\Services\Push\ApnsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWorkOrderPush implements ShouldQueue
{
    use Queueable;

    public ?int $queuedAtEpoch = null;

    public ?string $expectedSnapshot = null;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public int $userId,
        public int $workOrderId,
        public string $title,
        public string $body,
    ) {
        $this->queuedAtEpoch = now()->getTimestamp();
        $this->onQueue('ops-push');
        $subject = WorkOrder::find($this->workOrderId);
        $this->expectedSnapshot = $subject ? JobSnapshot::push($subject, $this->userId, $this->title) : null;
    }

    public function handle(ApnsClient $apns): void
    {
        if (! collect(['key_id', 'team_id', 'bundle_id', 'private_key'])->every(fn ($field) => filled(config('services.apns.'.$field)))) {
            throw new AutomationBlocked('Push credentials require review.');
        }
        app(ControlledJob::class)->run('push', WorkOrder::class, $this->workOrderId, $this->queuedAtEpoch, $this->expectedSnapshot,
            fn (WorkOrder $order) => JobSnapshot::push($order, $this->userId, $this->title),
            function () use ($apns): void {
                MobileDevice::where('user_id', $this->userId)->where('enabled', true)->eachById(function (MobileDevice $device) use ($apns): void {
                    $order = WorkOrder::find($this->workOrderId);
                    if (! $order || JobSnapshot::push($order, $this->userId, $this->title) !== $this->expectedSnapshot) {
                        throw new AutomationBlocked('Order changed before sending.');
                    }
                    $apns->send($device, ['aps' => ['alert' => ['title' => $this->title, 'body' => $this->body], 'sound' => 'default'], 'work_order_id' => $this->workOrderId]);
                });
            });
    }
}
