<?php

namespace App\Jobs;

use App\Models\AnalyticsReportSubscription;
use App\Notifications\AnalyticsReportNotification;
use App\Services\Analytics\AnalyticsExportService;
use App\Services\Analytics\AnalyticsFilter;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAnalyticsReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $subscriptionId) {}

    public function handle(AnalyticsExportService $exports, AnalyticsService $analytics): void
    {
        $subscription = AnalyticsReportSubscription::with('user')->find($this->subscriptionId);
        if (! $subscription?->enabled || ! $subscription->user) {
            return;
        }
        if (! in_array($subscription->area, AnalyticsFilter::allowedAreas($subscription->user), true)) {
            $subscription->update(['enabled' => false]);

            return;
        }

        $filter = AnalyticsFilter::fromStored($subscription->user, $subscription->area, $subscription->filters ?? []);
        $report = $exports->render($filter, $analytics, $subscription->format);
        $filename = 'analytics-'.$subscription->area.'-'.$filter->from->toDateString().'-'.$filter->to->toDateString().'.'.$report['extension'];
        $subscription->user->notify(new AnalyticsReportNotification(
            config("analytics.areas.{$subscription->area}"),
            $subscription->format,
            $filename,
            $report['contents'],
        ));
        $subscription->update(['last_sent_at' => now()]);
    }
}
