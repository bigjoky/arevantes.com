<?php

namespace App\Services\Crm;

use App\Models\CrmSetting;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\LeadResponseSlaBreachedNotification;
use App\Notifications\LeadResponseSlaEscalatedNotification;
use Illuminate\Support\Facades\DB;

class LeadResponseSlaNotifier
{
    public function notifyBreaches(): int
    {
        $count = 0;

        Opportunity::query()
            ->whereNotNull('owner_user_id')
            ->whereNull('first_responded_at')
            ->whereNull('first_response_sla_alerted_at')
            ->whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '<=', now())
            ->whereNotIn('stage', ['won', 'lost'])
            ->orderBy('id')
            ->chunkById(200, function ($opportunities) use (&$count): void {
                foreach ($opportunities as $opportunity) {
                    DB::transaction(function () use ($opportunity, &$count): void {
                        $locked = Opportunity::query()
                            ->with('owner')
                            ->lockForUpdate()
                            ->findOrFail($opportunity->id);

                        if (! $this->shouldNotify($locked)) {
                            return;
                        }

                        $locked->owner->notify(new LeadResponseSlaBreachedNotification($locked));
                        $locked->update(['first_response_sla_alerted_at' => now()]);
                        $count++;
                    });
                }
            });

        return $count;
    }

    public function escalatePersistentBreaches(): int
    {
        $count = 0;
        $escalateBefore = now()->subMinutes(CrmSetting::current()->lead_response_escalation_minutes);

        Opportunity::query()
            ->whereNull('first_responded_at')
            ->whereNull('first_response_sla_escalated_at')
            ->whereNotNull('first_response_sla_alerted_at')
            ->where('first_response_sla_alerted_at', '<=', $escalateBefore)
            ->whereNotIn('stage', ['won', 'lost'])
            ->orderBy('id')
            ->chunkById(200, function ($opportunities) use (&$count, $escalateBefore): void {
                foreach ($opportunities as $opportunity) {
                    DB::transaction(function () use ($opportunity, &$count, $escalateBefore): void {
                        $locked = Opportunity::query()
                            ->lockForUpdate()
                            ->findOrFail($opportunity->id);

                        if (! $this->shouldEscalate($locked, $escalateBefore)) {
                            return;
                        }

                        $recipients = User::query()->where('role', 'admin')->get();
                        if ($recipients->isEmpty()) {
                            return;
                        }

                        $recipients->each->notify(new LeadResponseSlaEscalatedNotification($locked));
                        $locked->update(['first_response_sla_escalated_at' => now()]);
                        $count++;
                    });
                }
            });

        return $count;
    }

    private function shouldNotify(Opportunity $opportunity): bool
    {
        return $opportunity->owner !== null
            && $opportunity->first_responded_at === null
            && $opportunity->first_response_sla_alerted_at === null
            && $opportunity->first_response_due_at?->isPast()
            && ! in_array($opportunity->stage, ['won', 'lost'], true);
    }

    private function shouldEscalate(Opportunity $opportunity, \DateTimeInterface $escalateBefore): bool
    {
        return $opportunity->first_responded_at === null
            && $opportunity->first_response_sla_escalated_at === null
            && $opportunity->first_response_sla_alerted_at?->lessThanOrEqualTo($escalateBefore)
            && ! in_array($opportunity->stage, ['won', 'lost'], true);
    }
}
