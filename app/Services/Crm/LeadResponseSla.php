<?php

namespace App\Services\Crm;

use App\Models\CrmActivity;
use App\Models\CrmSetting;
use App\Models\Opportunity;
use App\Support\Time\BusinessTime;

final class LeadResponseSla
{
    private const RESPONSE_TYPES = ['call', 'email', 'meeting', 'visit'];

    private const RESPONSE_STATUSES = ['in_progress', 'completed'];

    public function start(Opportunity $opportunity): void
    {
        if ($opportunity->first_response_due_at || in_array($opportunity->stage, ['won', 'lost'], true)) {
            return;
        }

        $startedAt = $opportunity->created_at ?? BusinessTime::now();
        $opportunity->updateQuietly([
            'first_response_due_at' => $startedAt->copy()->addMinutes(CrmSetting::current()->lead_response_sla_minutes),
        ]);
    }

    public function record(CrmActivity $activity): bool
    {
        if (! $activity->opportunity_id
            || ! in_array($activity->type, self::RESPONSE_TYPES, true)
            || ! in_array($activity->status, self::RESPONSE_STATUSES, true)) {
            return false;
        }

        return Opportunity::query()
            ->whereKey($activity->opportunity_id)
            ->whereNull('first_responded_at')
            ->update(BusinessTime::storage('first_responded_at', $activity->completed_at ?? BusinessTime::now())) === 1;
    }

    public function status(Opportunity $opportunity): string
    {
        if (! $opportunity->first_response_due_at) {
            return 'not_applicable';
        }

        if ($opportunity->first_responded_at) {
            return $opportunity->first_responded_at->lessThanOrEqualTo($opportunity->first_response_due_at)
                ? 'met'
                : 'breached';
        }

        if (in_array($opportunity->stage, ['won', 'lost'], true)) {
            return 'not_applicable';
        }

        return $opportunity->first_response_due_at->lt(BusinessTime::now()) ? 'breached' : 'pending';
    }

    public function responseMinutes(Opportunity $opportunity): ?int
    {
        return $opportunity->first_responded_at
            ? (int) round($opportunity->created_at->diffInSeconds($opportunity->first_responded_at) / 60)
            : null;
    }
}
