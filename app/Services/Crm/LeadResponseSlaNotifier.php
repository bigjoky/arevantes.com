<?php

namespace App\Services\Crm;

use App\Models\Opportunity;
use App\Notifications\LeadResponseSlaBreachedNotification;
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

    private function shouldNotify(Opportunity $opportunity): bool
    {
        return $opportunity->owner !== null
            && $opportunity->first_responded_at === null
            && $opportunity->first_response_sla_alerted_at === null
            && $opportunity->first_response_due_at?->isPast()
            && ! in_array($opportunity->stage, ['won', 'lost'], true);
    }
}
