<?php

namespace App\Services\Work;

use App\Models\Installation;
use Illuminate\Validation\ValidationException;

class TechnicalExecutionGuard
{
    public function validated(Installation $installation): bool
    {
        return $installation->workOrders()->where('kind', 'survey')->where('status', 'completed')->exists()
            || $installation->engineeringDossiers()->whereIn('status', ['approved', 'handed_over'])->exists();
    }

    public function assertCanPrepare(Installation $installation): void
    {
        if ($installation->status === 'cancelled' || $installation->dossier_closed_at) {
            throw ValidationException::withMessages(['status' => 'Reabre el expediente cancelado o cerrado antes de preparar otra instalación.']);
        }
    }

    public function assertAllows(Installation $installation, string $kind): void
    {
        if (! in_array($kind, ['installation', 'commissioning'], true)) {
            return;
        }
        $this->assertCanPrepare($installation);
        if ($installation->technical_validation_required === true && ! $this->validated($installation)) {
            throw ValidationException::withMessages(['status' => 'Falta la validación técnica: completa la visita o aprueba el expediente de ingeniería. Puedes preparar la orden mientras tanto.']);
        }
    }
}
