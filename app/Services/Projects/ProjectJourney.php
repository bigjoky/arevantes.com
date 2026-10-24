<?php

namespace App\Services\Projects;

use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Inventory\ProjectMaterialPlanner;
use App\Services\Quotes\QuoteMaterialReadiness;
use App\Services\Work\TechnicalExecutionGuard;
use App\Services\Work\WorkOrderPlanningReadiness;
use App\Support\Crm\OpportunityCatalog;
use Illuminate\Support\Facades\DB;

class ProjectJourney
{
    public function for(User $user, ?Installation $installation = null, ?Opportunity $opportunity = null): array
    {
        $installation ??= $opportunity?->installation;
        $quotes = Quote::query()->when($installation, fn ($q) => $q->where('installation_id', $installation->id), fn ($q) => $q->where('opportunity_id', $opportunity?->id));
        $quote = (clone $quotes)->where('status', 'accepted')->latest('id')->first() ?? $quotes->latest('id')->first();
        $opportunity ??= $quote?->opportunity ?? $installation?->opportunities()->latest('id')->first();
        $blocks = [];
        $materialRows = [];
        $block = function (string $key, string $title, string $state, string $detail, ?string $owner, ?string $href, ?string $action = null) use (&$blocks) {
            $blocks[] = compact('key', 'title', 'state', 'detail', 'owner', 'href', 'action');
        };
        if ($user->can('manage-customers')) {
            $block('commercial', 'Situación comercial', $opportunity ? ($opportunity->stage === 'won' ? 'complete' : 'pending') : 'unrecorded', $opportunity ? $opportunity->code.' · '.(OpportunityCatalog::STAGES[$opportunity->stage] ?? $opportunity->stage) : 'Sin oportunidad vinculada.', $opportunity?->owner?->name, $opportunity ? '/opportunities/'.$opportunity->id : null, 'Abrir oportunidad');
        } else {
            $block('commercial', 'Situación comercial', 'unavailable', 'Sin permiso para consultar el CRM.', null, null);
        }
        if ($user->can('manage-quotes')) {
            $history = $quote?->statusHistory()->where('to_status', 'accepted')->latest('id')->first();
            $block('quote', 'Presupuesto y aceptación', $quote ? ($quote->status === 'accepted' ? 'complete' : 'pending') : 'unrecorded', $quote ? $quote->number.' · '.(['draft' => 'Borrador', 'sent' => 'Enviado', 'accepted' => 'Aceptado', 'rejected' => 'Rechazado', 'expired' => 'Caducado'][$quote->status] ?? $quote->status).($history?->channel === 'paper' ? ' · firmado en papel por '.$history->signed_by_name : '') : 'Sin presupuesto registrado.', $quote?->creator?->name, $quote ? '/quotes/'.$quote->id : ($opportunity ? '/quotes/create?opportunity='.$opportunity->id : '/quotes/create'), $quote ? 'Revisar presupuesto' : 'Preparar presupuesto');
        } else {
            $block('quote', 'Presupuesto y aceptación', 'unavailable', 'Sin permiso para consultar presupuestos.', null, null);
        }
        $block('project', 'Proyecto', $installation ? 'complete' : 'unrecorded', $installation?->project_reference ?: ($installation ? 'Instalación vinculada sin referencia de proyecto.' : 'El presupuesto aceptado todavía no tiene proyecto.'), $installation?->commercial?->name, $installation && $user->can('view-installations') ? '/execution/projects/'.$installation->id : ($quote && $user->can('manage-quotes') && $user->can('manage-installations') ? '/quotes/'.$quote->id : null), $installation ? 'Abrir proyecto' : 'Crear proyecto desde el presupuesto');
        if (! $installation) {
            return ['blocks' => $blocks, 'closure' => null, 'next' => collect($blocks)->first(fn ($b) => in_array($b['state'], ['pending', 'unrecorded'], true) && $b['href'])];
        }
        $canOperate = $user->can('manage-operations');
        $orders = $canOperate ? $installation->workOrders()->when($user->hasRole('technician'), fn ($q) => $q->where('assigned_user_id', $user->id))->with('assignedUser:id,name')->orderBy('id')->get() : collect();
        $execution = $orders->whereIn('kind', ['installation', 'commissioning']);
        $technical = $canOperate && app(TechnicalExecutionGuard::class)->validated($installation);
        $required = $installation->technical_validation_required;
        $survey = $orders->where('kind', 'survey')->where('status', '!=', 'cancelled')->sortByDesc('id')->first();
        $executionOwner = $execution->map(fn ($order) => $order->assignedUser?->name)->filter()->unique()->join(' · ') ?: $installation->technician?->name;
        $technicalOwner = $survey?->assignedUser?->name ?: $installation->technician?->name;
        $technicalHref = $survey ? '/work-orders/manage?work_order='.$survey->id : '/work-orders/create?installation='.$installation->id.'&kind=survey';
        $materialsHref = $execution->first() ? '/work-orders/manage?work_order='.$execution->first()->id : '/work-orders/create?installation='.$installation->id;
        $block('technical', 'Visita y validación técnica', ! $canOperate ? 'unavailable' : ($technical ? 'complete' : ($required === false ? 'not_required' : ($required === true ? 'blocked' : 'unrecorded'))), ! $canOperate ? 'Sin permiso para consultar operaciones.' : ($technical ? 'Hay una visita completada o un expediente de ingeniería aprobado.' : ($required === false ? 'Este proyecto no requiere validación técnica previa.' : ($required === true ? 'Completa una visita técnica o aprueba ingeniería antes de ejecutar.' : 'El requisito técnico todavía no está definido.'))), $technicalOwner, $canOperate ? $technicalHref : null, 'Revisar validación técnica');
        $finance = $user->can('view-finance');
        if ($finance) {
            $docs = SalesDocument::where('installation_id', $installation->id)->where('type', 'invoice')->withSum('payments as collected', 'amount')->get();
            $issued = (float) $docs->where('status', '!=', 'draft')->sum('amount');
            $collected = (float) $docs->sum('collected');
            $drafts = $docs->where('status', 'draft');
            $collectedDrafts = $drafts->filter(fn ($d) => (float) $d->collected >= (float) $d->amount && (float) $d->amount > 0)->count();
            $financialPending = in_array($installation->financial_status, ['pending', 'partial'], true);
            $expected = $quote && $quote->status === 'accepted' ? (float) $quote->amount : null;
            $financeState = $financialPending ? 'blocked' : ($expected === null ? 'unrecorded' : ($expected <= 0 ? 'not_required' : ($issued < $expected || $collected < $expected || $drafts->isNotEmpty() ? 'pending' : 'complete')));
            $advanceLabel = $quote && (float) $quote->acceptance_payment_percentage <= 0 ? 'no requerido' : (['pending' => 'pendiente', 'partial' => 'parcialmente cobrado', 'cleared' => 'confirmado', 'not_required' => 'no requerido'][$installation->financial_status] ?? 'sin definir');
            $block('finance', 'Cobros y facturas', $financeState, sprintf('Cobrado: %.2f € · Facturado: %.2f € · %d hitos cobrados pendientes de facturar. Anticipo: %s.', $collected, $issued, $collectedDrafts, $advanceLabel), null, $quote && $user->can('manage-quotes') ? '/quotes/'.$quote->id.'?tab=billing' : '/billing/payments', 'Revisar cobros y facturación');
        } else {
            $block('finance', 'Cobros y facturas', 'unavailable', 'Sin permiso para consultar importes ni cobros.', null, null);
        }
        if ($canOperate) {
            $plan = app(ProjectMaterialPlanner::class)->summary($installation);
            $unmapped = $quote ? count(app(QuoteMaterialReadiness::class)->classificationWarnings($quote)) : 0;
            $purchases = $user->can('view-purchases') ? DB::table('purchase_document_lines')->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
                ->where('purchase_documents.installation_id', $installation->id)->where('purchase_documents.type', 'purchase_order')->whereIn('purchase_documents.status', ['ordered', 'partially_received', 'received'])
                ->groupBy('purchase_document_lines.product_id')->selectRaw('purchase_document_lines.product_id, sum(quantity) as ordered, sum(received_quantity - returned_quantity) as received')->get()->keyBy('product_id') : collect();
            $materialRows = collect($plan['rows'])->map(fn ($row) => [
                'description' => $row['description'], 'planned' => $row['planned_quantity'], 'consumed' => $row['actual_quantity'], 'reserved' => $row['reserved_quantity'], 'available' => $row['available_quantity'],
                'ordered' => $user->can('view-purchases') ? (float) ($purchases[$row['product_id']]->ordered ?? 0) : null,
                'received' => $user->can('view-purchases') ? (float) ($purchases[$row['product_id']]->received ?? 0) : null,
            ])->all();
            $remaining = collect($plan['rows'])->sum('remaining_quantity');
            $available = $remaining <= collect($plan['rows'])->sum(fn ($r) => min($r['remaining_quantity'], $r['available_quantity'] + $r['reserved_quantity']));
            $purchaseCount = $user->can('view-purchases') ? $installation->purchaseDocuments()->where('type', 'purchase_order')->whereIn('status', ['ordered', 'partially_received'])->count() : null;
            $block('materials', 'Materiales y compras', $unmapped ? 'pending' : (count($plan['rows']) === 0 ? 'unrecorded' : ($available ? 'complete' : 'blocked')), count($plan['rows']).' productos planificados · '.$unmapped.' conceptos o materiales con clasificación logística pendiente.'.($purchaseCount !== null ? ' '.$purchaseCount.' pedidos pendientes de recepción.' : ' Compras no disponibles para este perfil.').' Comprado, recibido, reservado y consumido son estados distintos.', null, $materialsHref, 'Revisar materiales');
            $pendingOrders = $execution->whereNotIn('status', ['completed', 'cancelled']);
            $issues = [];
            foreach ($pendingOrders as $order) {
                $ready = app(WorkOrderPlanningReadiness::class)->for($order);
                if (! $order->scheduled_start || ! $order->scheduled_end) {
                    $issues[] = $order->number.': fecha o duración pendiente';
                }
                if (! $ready['personnel_ready']) {
                    $issues[] = $order->number.': técnico pendiente, no cualificado o no disponible';
                }
                if (! $ready['material_ready_now']) {
                    $issues[] = $order->number.': materiales aún no disponibles físicamente';
                }
            }
            $block('planning', 'Planificación y personal', $execution->isEmpty() ? 'unrecorded' : ($issues ? 'blocked' : 'complete'), $execution->isEmpty() ? 'Todavía no hay órdenes de instalación o puesta en marcha.' : ($issues ? implode(' · ', $issues) : 'Las órdenes tienen fecha, duración y personal preparado.'), $executionOwner, $execution->isEmpty() && $user->can('manage-installations') ? '/work-orders/create?installation='.$installation->id : '/work-orders?installation='.$installation->id, 'Preparar órdenes de trabajo');
            $done = $execution->where('status', 'completed')->count();
            $block('execution', 'Ejecución', $execution->isEmpty() ? 'unrecorded' : ($done > 0 && $pendingOrders->isEmpty() ? 'complete' : 'pending'), $done.' órdenes terminadas · '.$pendingOrders->count().' pendientes. La instalación terminada no cierra el expediente.', $executionOwner, '/work-orders?installation='.$installation->id, 'Revisar ejecución');
            $tasks = $installation->activities()->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])->count();
            $mandatory = $installation->activities()->where('required_for_closure', true)->where('status', '!=', 'completed')->count();
            $block('delivery', 'Documentación y legalización', $installation->dossier_delivery_confirmed_at && ! $mandatory ? 'complete' : ($mandatory ? 'blocked' : 'pending'), $installation->dossier_delivery_confirmed_at && ! $mandatory ? 'Entrega confirmada por una persona responsable. Las tareas informativas no bloquean el cierre.' : ($tasks ? $mandatory.' obligatorias para el cierre; '.$tasks.' tareas del proyecto pendientes. Revisa cuáles condicionan la entrega.' : 'Sin tareas documentales pendientes registradas. Confirma expresamente la revisión y entrega; la ausencia de tareas no acredita legalización.'), null, '/operations/activities?installation='.$installation->id, 'Revisar entrega y documentación');
        } else {
            foreach (['materials' => 'Materiales y compras', 'planning' => 'Planificación y personal', 'execution' => 'Ejecución', 'delivery' => 'Documentación y legalización'] as $key => $title) {
                $block($key, $title, 'unavailable', 'Sin permiso para consultar operaciones.', null, null);
            }
        }
        $economicException = $installation->dossier_closed_at && (bool) data_get(AuditLog::where('auditable_type', $installation->getMorphClass())->where('auditable_id', $installation->id)->where('event', 'project_dossier_close')->latest('id')->first()?->new_values, 'closure_basis.economic_exception', false);
        $block('closure', 'Cierre del expediente', $installation->dossier_closed_at ? 'complete' : 'pending', $installation->dossier_closed_at ? ($economicException ? 'Cerrado con excepción económica. ' : '').'Cerrado el '.$installation->dossier_closed_at->format('d/m/Y H:i').'. '.$installation->dossier_closure_reason : 'Abierto. El cierre administrativo es una acción expresa posterior a la ejecución.', null, null);

        return ['materials' => $materialRows, 'blocks' => $blocks, 'next' => collect($blocks)->first(fn ($b) => in_array($b['state'], ['blocked', 'pending', 'unrecorded'], true) && $b['href']), 'closure' => ['closed_at' => $installation->dossier_closed_at?->toISOString(), 'can_manage' => $user->can('manage-admin'), 'installation_id' => $installation->id, 'technical_required' => $required, 'revision' => (int) $installation->dossier_revision, 'updated_at' => $installation->updated_at->toISOString(), 'delivery_confirmed_at' => $installation->dossier_delivery_confirmed_at?->toISOString(), 'tasks' => $user->can('manage-admin') ? $installation->activities()->whereNull('work_order_id')->orderBy('id')->get(['id', 'title', 'status', 'required_for_closure'])->toArray() : []]];
    }
}
