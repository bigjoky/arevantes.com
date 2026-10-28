<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationBlocked;
use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/** One guarded execution per business revision; external uncertainty requires human review. */
class ControlledJob
{
    /** @param class-string<Model> $model */
    public function run(string $kind, string $model, int $id, ?int $queuedAt, ?string $expected, Closure $snapshot, Closure $effect): void
    {
        if (! config('automation.approved.'.$kind, false)) {
            throw new AutomationBlocked('Integration not approved for controlled execution.');
        }
        $budget = ['push' => 90, 'arevia' => 480, 'geocode' => 90][$kind] ?? null;
        if ($budget === null || (int) config('queue.connections.database.retry_after', 0) <= $budget + 30) {
            throw new AutomationBlocked('Queue reservation must exceed execution timeout and safety margin.');
        }
        $age = $queuedAt === null ? null : now()->getTimestamp() - $queuedAt;
        if ($expected === null || $age === null || $age < -30 || $age > (int) config('automation.max_age_seconds.'.$kind, 0)) {
            throw new AutomationBlocked('Legacy, expired or invalid job: review without automatic replay.');
        }
        if (DB::transactionLevel() > 0 && ! app()->environment('testing')) {
            throw new AutomationBlocked('External work requires committed asynchronous dispatch.');
        }
        $key = hash('sha256', $kind.'|'.$model.'|'.$id.'|'.$expected);
        $subject = DB::transaction(function () use ($model, $id, $snapshot, $expected, $kind, $key): ?Model {
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                DB::statement("SET LOCAL lock_timeout = '2s'");
            }
            $row = $model::query()->lockForUpdate()->find($id);
            if (! $row) {
                throw new AutomationBlocked('Business object no longer exists.');
            }
            if (AuditLog::where('auditable_type', $model)->where('auditable_id', $id)->where('event', 'automation_effect_claimed')->where('new_values->key', $key)->exists()) {
                return null;
            }
            $current = $snapshot($row);
            if ($current === null || ! hash_equals($expected, $current)) {
                throw new AutomationBlocked('Business state, recipient or permissions changed.');
            }
            $this->record($row, 'automation_effect_claimed', $kind, $key);

            return $row;
        });
        if (! $subject) {
            return;
        }
        try {
            $effect($subject);
            $this->record($subject, 'automation_effect_completed', $kind, $key);
        } catch (Throwable $exception) {
            // Never remove the claim: acceptance by an external service may be unknown.
            $this->record($subject, 'automation_effect_uncertain', $kind, $key, $exception::class);
            throw new AutomationBlocked('External attempt requires review before retrying.', previous: $exception);
        }
    }

    private function record(Model $subject, string $event, string $kind, string $key, ?string $error = null): void
    {
        $at = now()->utc();
        AuditLog::create(['event' => $event, 'auditable_type' => $subject::class, 'auditable_id' => $subject->getKey(), 'new_values' => ['kind' => $kind, 'key' => $key, 'error_class' => $error, 'recorded_at_utc' => $at->toISOString(), 'recorded_at_epoch' => $at->getTimestamp()]]);
    }
}
