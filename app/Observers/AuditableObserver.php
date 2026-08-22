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

        $sensitive = [
            'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'mailbox_password',
            'tax_id', 'document_number', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone',
            'annual_reference_salary', 'phone', 'prevention_notes', 'notes', 'decision_notes',
        ];
        $sanitize = fn (?array $values) => $values === null ? null : collect($values)->mapWithKeys(
            fn ($value, $key) => [$key => in_array($key, $sensitive, true) ? '[REDACTED]' : $value]
        )->all();

        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $sanitize($old),
            'new_values' => $sanitize($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'correlation_id' => $request?->attributes->get('correlation_id'),
            'route' => $request?->route()?->getName(),
            'method' => $request?->method(),
        ]);
    }
}
