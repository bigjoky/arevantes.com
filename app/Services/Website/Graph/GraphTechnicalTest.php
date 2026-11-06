<?php

namespace App\Services\Website\Graph;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WebsiteGraphTestAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class GraphTechnicalTest
{
    public function authorize(User $actor, string $id): void
    {
        abort_unless($actor->can('manage-admin'), 403);
        $this->requireEnabled();
        DB::transaction(function () use ($actor, $id): void {
            $this->lockCampaign();
            $this->requireUnusedCampaign();
            $existing = WebsiteGraphTestAuthorization::query()->whereKey($id)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->authorized_by === $actor->id, 403);
                return;
            }
            $item = WebsiteGraphTestAuthorization::create(['id' => $id, 'authorized_by' => $actor->id, 'status' => 'authorized']);
            $this->audit($item, $actor, 'graph_test_authorized');
        });
    }

    public function execute(User $actor, string $id): string
    {
        abort_unless($actor->can('manage-admin'), 403);
        $this->requireEnabled();
        if (config('contact-mail.transport') !== 'graph') {
            throw ValidationException::withMessages(['test' => 'El transporte Graph no está configurado.']);
        }
        $item = DB::transaction(function () use ($actor, $id) {
            $this->lockCampaign();
            $this->requireUnusedCampaign();
            $item = WebsiteGraphTestAuthorization::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($item->status !== 'authorized') {
                throw ValidationException::withMessages(['test' => 'Esta autorización ya se ha utilizado o su resultado es incierto.']);
            }
            $item->update(['status' => 'sending', 'executed_by' => $actor->id, 'attempted_at' => now()]);
            $this->audit($item, $actor, 'graph_test_started');
            return $item;
        });
        try {
            app(GraphMailer::class)->sendTechnicalTest($item->id);
            $status = 'accepted';
            $error = null;
        } catch (Throwable $e) {
            $status = 'uncertain';
            $error = $e instanceof GraphException ? $e->category : 'internal_error';
        }
        DB::transaction(function () use ($item, $actor, $status, $error): void {
            $locked = WebsiteGraphTestAuthorization::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'sending') {
                throw new \RuntimeException('Estado de prueba incompatible.');
            }
            $locked->update(['status' => $status, 'accepted_at' => $status === 'accepted' ? now() : null, 'error_class' => $error]);
            $this->audit($locked, $actor, 'graph_test_'.$status);
        });
        return $status;
    }

    public function confirmReceipt(User $actor, string $id): void
    {
        abort_unless($actor->can('manage-admin'), 403);
        DB::transaction(function () use ($actor, $id): void {
            $item = WebsiteGraphTestAuthorization::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($item->status !== 'accepted' || $item->receipt_confirmed_at !== null) {
                throw ValidationException::withMessages(['test' => 'No se puede registrar esta confirmación.']);
            }
            $item->update(['receipt_confirmed_at' => now(), 'receipt_confirmed_by' => $actor->id]);
            $this->audit($item, $actor, 'graph_test_receipt_confirmed');
        });
    }

    private function requireEnabled(): void
    {
        if (config('contact-mail.graph_test_enabled') !== true) {
            throw ValidationException::withMessages(['test' => 'La prueba técnica está desactivada.']);
        }
    }

    private function lockCampaign(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select("SELECT pg_advisory_xact_lock(hashtextextended('graph-technical-test:initial-campaign', 0))");
        }
    }

    private function requireUnusedCampaign(): void
    {
        if (WebsiteGraphTestAuthorization::query()->whereNotNull('attempted_at')->exists()) {
            throw ValidationException::withMessages(['test' => 'El único intento de esta campaña ya se ha consumido.']);
        }
    }

    private function audit(WebsiteGraphTestAuthorization $item, User $actor, string $event): void
    {
        AuditLog::create(['user_id' => $actor->id, 'event' => $event, 'auditable_type' => User::class, 'auditable_id' => $actor->id,
            'new_values' => ['test_id' => $item->id, 'status' => $item->status]]);
    }
}
