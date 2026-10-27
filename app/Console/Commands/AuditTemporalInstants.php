<?php

namespace App\Console\Commands;

use App\Support\Time\BusinessTime;
use Illuminate\Console\Command;

class AuditTemporalInstants extends Command
{
    protected $signature = 'time:audit-instants {--json} {--strict : Return failure for divergent, ambiguous or invalid pairs}';

    protected $description = 'Read-only inventory of precise and ambiguous legacy timestamps; never converts history';

    public function handle(): int
    {
        $report = [];
        foreach (['DocumentDelivery', 'CustomerPortalAccess', 'WorkOrderAttachment', 'PortfolioProject', 'PlanningScenario', 'Opportunity', 'WorkOrder', 'TimeEntry', 'CrmActivity', 'WorkOrderReschedule'] as $name) {
            $class = 'App\\Models\\'.$name;
            $model = new $class;
            $fields = $model->preciseInstantFields();
            foreach ($fields as $field) {
                $report[$model->getTable().'.'.$field] = ['precise' => 0, 'legacy_unambiguous' => 0, 'ambiguous' => 0, 'invalid_or_nonexistent' => 0, 'divergent' => 0, 'empty' => 0];
            }
            $model->newQueryWithoutScopes()->select(['id', ...$fields, ...array_map(fn ($field) => $field.'_utc', $fields)])->chunkById(200, function ($rows) use (&$report, $model, $fields): void {
                foreach ($rows as $row) {
                    foreach ($fields as $field) {
                        $kind = BusinessTime::classify($row->getRawOriginal($field), $row->getRawOriginal($field.'_utc'));
                        $report[$model->getTable().'.'.$field][$kind]++;
                    }
                }
            });
        }
        $this->line(json_encode(['timezone' => BusinessTime::zone(), 'read_only' => true, 'fields' => $report], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $unsafe = collect($report)->sum(fn ($counts) => $counts['divergent'] + $counts['ambiguous'] + $counts['invalid_or_nonexistent']);

        return $this->option('strict') && $unsafe > 0 ? self::FAILURE : self::SUCCESS;
    }
}
