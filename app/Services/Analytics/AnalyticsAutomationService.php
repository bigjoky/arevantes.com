<?php

namespace App\Services\Analytics;

use App\Jobs\SendAnalyticsReport;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReportSubscription;
use App\Notifications\AnalyticsThresholdNotification;
use Carbon\CarbonImmutable;

class AnalyticsAutomationService
{
    /** @return array{alerts_evaluated: int, alerts_triggered: int, reports_queued: int} */
    public function dispatch(): array
    {
        $result = ['alerts_evaluated' => 0, 'alerts_triggered' => 0, 'reports_queued' => 0];

        AnalyticsAlert::query()->with('user')->where('enabled', true)->eachById(function (AnalyticsAlert $alert) use (&$result): void {
            $user = $alert->user;
            $definition = config("analytics.metrics.{$alert->metric_key}");
            if (! $user || ! $alert->area || ! $definition || ! in_array($alert->area, AnalyticsFilter::allowedAreas($user), true) || ! in_array($alert->area, $definition['areas'], true)) {
                $alert->update(['enabled' => false]);

                return;
            }

            $filter = AnalyticsFilter::fromStored($user, $alert->area, $alert->filters ?? []);
            $value = app(AnalyticsService::class)->value($alert->metric_key, $filter, $filter->from, $filter->to);
            $triggered = $this->matches($value, $alert->operator, (float) $alert->threshold);
            $becameTriggered = $triggered && ! $alert->is_triggered;
            if ($becameTriggered) {
                $user->notify(new AnalyticsThresholdNotification(
                    $definition['name'], $value, $alert->operator, (float) $alert->threshold,
                    $alert->area, $alert->channel,
                ));
                $result['alerts_triggered']++;
            }
            $alert->update([
                'last_value' => $value,
                'is_triggered' => $triggered,
                'last_evaluated_at' => now(),
                'last_triggered_at' => $becameTriggered ? now() : $alert->last_triggered_at,
            ]);
            $result['alerts_evaluated']++;
        });

        AnalyticsReportSubscription::query()->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('next_send_at')->orWhere('next_send_at', '<=', now()))
            ->eachById(function (AnalyticsReportSubscription $subscription) use (&$result): void {
                $subscription->update(['next_send_at' => $this->nextSendAt($subscription->frequency, $subscription->timezone)]);
                SendAnalyticsReport::dispatch($subscription->id);
                $result['reports_queued']++;
            });

        return $result;
    }

    public function nextSendAt(string $frequency, string $timezone = 'Europe/Madrid'): CarbonImmutable
    {
        $now = CarbonImmutable::now($timezone);

        return match ($frequency) {
            'daily' => $now->addDay()->startOfDay()->addHours(7),
            'monthly' => $now->addMonth()->startOfMonth()->addHours(7),
            default => $now->addWeek()->startOfWeek()->addHours(7),
        };
    }

    private function matches(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            '=' => abs($value - $threshold) < 0.005,
            default => false,
        };
    }
}
