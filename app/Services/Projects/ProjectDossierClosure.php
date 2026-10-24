<?php

namespace App\Services\Projects;

use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectDossierClosure
{
    public function record(Installation $installation, User $actor, array $data): void
    {
        abort_unless($actor->can('manage-admin'), 403);
        DB::transaction(function () use ($installation, $actor, $data) {
            Quote::where('installation_id', $installation->id)->orderBy('id')->lockForUpdate()->get(['id']);
            $locked = Installation::lockForUpdate()->findOrFail($installation->id);
            if ((int) $locked->dossier_revision !== (int) $data['expected_revision'] || $locked->updated_at->toISOString() !== Carbon::parse($data['expected_updated_at'])->toISOString()) {
                throw ValidationException::withMessages(['expected_updated_at' => 'El expediente ha cambiado. Recarga antes de registrar la decisión.']);
            }
            $basis = [];
            $before = $locked->only(['technical_validation_required', 'dossier_closed_at', 'dossier_closed_by', 'dossier_closure_reason', 'dossier_delivery_confirmed_at', 'dossier_delivery_confirmed_by']);
            if ($data['action'] === 'set_required_tasks') {
                abort_if($locked->dossier_closed_at, 422, 'Reabre el expediente para cambiar requisitos.');
                $ids = $data['required_activity_ids'] ?? [];
                $activities = $locked->activities()->whereNull('work_order_id')->lockForUpdate()->get();
                abort_unless(collect($ids)->diff($activities->pluck('id'))->isEmpty(), 422, 'Las tareas deben pertenecer a este expediente.');
                $before['required_activity_ids'] = $activities->where('required_for_closure', true)->pluck('id')->all();
                foreach ($activities as $activity) {
                    $activity->update(['required_for_closure' => in_array($activity->id, $ids)]);
                }
                $locked->update(['dossier_delivery_confirmed_at' => null, 'dossier_delivery_confirmed_by' => null]);
            } elseif ($data['action'] === 'confirm_delivery') {
                abort_if($locked->dossier_closed_at, 422, 'El expediente ya está cerrado.');
                if ($locked->activities()->where('required_for_closure', true)->where('status', '!=', 'completed')->exists()) {
                    throw ValidationException::withMessages(['action' => 'Completa las tareas documentales obligatorias antes de confirmar la entrega.']);
                }
                $locked->update(['dossier_delivery_confirmed_at' => now(), 'dossier_delivery_confirmed_by' => $actor->id]);
            } elseif (in_array($data['action'], ['require_technical', 'waive_technical'], true)) {
                abort_if($locked->dossier_closed_at, 422, 'Reabre el expediente para cambiar sus requisitos.');
                $locked->update(['technical_validation_required' => $data['action'] === 'require_technical']);
            } elseif ($data['action'] === 'reopen') {
                if (! $locked->dossier_closed_at) {
                    return;
                }
                $locked->update(['dossier_closed_at' => null, 'dossier_closed_by' => null, 'dossier_closure_reason' => null, 'dossier_delivery_confirmed_at' => null, 'dossier_delivery_confirmed_by' => null]);
            } else {
                if ($locked->dossier_closed_at) {
                    return;
                }
                $orders = $locked->workOrders()->whereIn('kind', ['installation', 'commissioning'])->lockForUpdate()->get();
                if (! $orders->contains('status', 'completed') || $orders->whereNotIn('status', ['completed', 'cancelled'])->isNotEmpty()) {
                    throw ValidationException::withMessages(['action' => 'Completa las órdenes de instalación y puesta en marcha pendientes antes del cierre administrativo.']);
                }
                if (! $locked->dossier_delivery_confirmed_at || $locked->activities()->where('required_for_closure', true)->where('status', '!=', 'completed')->exists()) {
                    throw ValidationException::withMessages(['action' => 'Revisa los requisitos documentales y confirma expresamente la entrega antes de cerrar.']);
                }
                $quote = $locked->quotes()->where('status', 'accepted')->latest('id')->first();
                $documents = SalesDocument::where('installation_id', $locked->id)->whereIn('type', ['invoice', 'credit_note'])->lockForUpdate()->get();
                $issued = $documents->where('status', '!=', 'draft');
                $invoiced = round((float) $issued->sum('amount'), 2);
                $invoiceIds = $documents->where('type', 'invoice')->pluck('id');
                $collected = round((float) SalesDocumentPayment::whereIn('sales_document_id', $invoiceIds)->sum('amount'), 2);
                if (! $quote) {
                    throw ValidationException::withMessages(['action' => 'Se requiere un presupuesto aceptado para cerrar el expediente.']);
                }
                $economicPending = $invoiced < round((float) $quote->amount, 2) || $collected < round((float) $quote->amount, 2) || $documents->where('type', 'invoice')->where('status', 'draft')->isNotEmpty();
                if ($economicPending && ! ($data['economic_exception'] ?? false)) {
                    throw ValidationException::withMessages(['action' => 'Hay cobros o facturas pendientes. Resuélvelos o autoriza expresamente una excepción económica con motivo como administrador.']);
                }
                $basis = ['economic_exception' => $economicPending, 'economic_exception_reason' => $economicPending ? $data['reason'] : null, 'draft_invoice_ids' => $documents->where('type', 'invoice')->where('status', 'draft')->pluck('id')->all(), 'quote_id' => $quote->id, 'quote_version' => $quote->version, 'contract_amount' => $quote->amount, 'invoiced_amount' => $invoiced, 'collected_amount' => $collected, 'completed_order_ids' => $orders->where('status', 'completed')->pluck('id')->all()];
                $locked->update(['dossier_closed_at' => now(), 'dossier_closed_by' => $actor->id, 'dossier_closure_reason' => $data['reason']]);
            }
            $locked->increment('dossier_revision');
            AuditLog::create(['user_id' => $actor->id, 'event' => 'project_dossier_'.$data['action'], 'auditable_type' => $locked->getMorphClass(), 'auditable_id' => $locked->id, 'old_values' => $before, 'new_values' => [...$locked->only(array_keys($before)), 'required_activity_ids' => $locked->activities()->where('required_for_closure', true)->pluck('id')->all(), 'dossier_revision' => $locked->dossier_revision, 'closure_basis' => $basis, 'reason' => $data['reason']]]);
        });
    }
}
