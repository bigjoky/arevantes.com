<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes !== []) {
            $this->record($model, 'updated', array_intersect_key($model->getOriginal(), $changes), $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getOriginal(), null);
    }

    private function record(Model $model, string $event, ?array $old, ?array $new): void
    {
        if (app()->runningInConsole() && auth()->guest()) {
            return;
        }

        $request = app()->bound('request') ? request() : null;

        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
