<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WebsiteContactReceipt;
use App\Models\WebsiteContactMail;
use App\Models\WebsiteGraphTestAuthorization;
use App\Services\Website\Graph\GraphTechnicalTest;
use App\Services\Website\WebsiteContactReceipts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteContactIncidentsController extends Controller
{
    public function index(): Response
    {
        $receipts = WebsiteContactReceipt::query()->whereNull('processed_at')->latest()->paginate(25)
            ->through(fn (WebsiteContactReceipt $receipt) => [
                'id' => $receipt->id,
                'created_at' => $receipt->created_at?->toIso8601String(),
                'expires_at' => $receipt->expires_at?->toIso8601String(),
                'payload' => $receipt->payload,
            ]);

        $mail = WebsiteContactMail::query()->where('status', 'pending')->where('attempts', 0)
            ->whereNull('attempted_at')->whereNull('sent_at')->orderBy('id')->paginate(25, ['id', 'opportunity_id', 'receipt_id', 'kind', 'created_at'], 'mail_page');

        // The feature can be deployed before its separately authorized migration.
        $graphTestAvailable = \Illuminate\Support\Facades\Schema::hasTable('website_graph_test_authorizations');
        $graphCampaignConsumed = $graphTestAvailable && WebsiteGraphTestAuthorization::query()->whereNotNull('attempted_at')->exists();

        return Inertia::render('notifications/contact-incidents', ['receipts' => $receipts, 'mail' => $mail,
            'graphTestAvailable' => $graphTestAvailable,
            'graphCampaignConsumed' => $graphCampaignConsumed,
            'graphTestEnabled' => $graphTestAvailable && config('contact-mail.graph_test_enabled') === true,
            'graphTestId' => (string) \Illuminate\Support\Str::uuid(),
            'graphTests' => $graphTestAvailable ? WebsiteGraphTestAuthorization::query()->latest()->limit(20)->get(['id', 'status', 'created_at', 'accepted_at', 'receipt_confirmed_at']) : []]);
    }

    public function retry(WebsiteContactReceipt $receipt, WebsiteContactReceipts $service): RedirectResponse
    {
        $processed = $service->process($receipt);
        AuditLog::create(['user_id' => auth()->id(), 'event' => 'website_contact_retry', 'auditable_type' => $receipt->getMorphClass(), 'auditable_id' => $receipt->id, 'new_values' => ['processed' => $processed]]);

        return back()->with('toast', [
            'type' => $processed ? 'success' : 'error',
            'message' => $processed ? 'Solicitud procesada en el CRM.' : 'No se ha podido procesar. La solicitud sigue pendiente.',
        ]);
    }

    public function cancelMail(Request $request, WebsiteContactMail $mail): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Indica el motivo de la cancelación.']);
        }
        DB::transaction(function () use ($mail, $request, $reason): void {
            $item = WebsiteContactMail::query()->lockForUpdate()->findOrFail($mail->id);
            if ($item->status !== 'pending' || $item->attempts !== 0 || $item->attempted_at !== null || $item->sent_at !== null) {
                throw ValidationException::withMessages(['reason' => 'El envío ya no se puede cancelar como pendiente.']);
            }
            $item->update(['status' => 'cancelled']);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'contact_mail_pending_cancelled',
                'auditable_type' => $item->getMorphClass(),
                'auditable_id' => $item->id,
                'old_values' => ['status' => 'pending', 'attempts' => 0],
                'new_values' => ['status' => 'cancelled', 'reason' => $reason],
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Correo pendiente cancelado.']);
    }

    public function authorizeGraphTest(Request $request, GraphTechnicalTest $service): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'uuid'], 'recipient' => ['required', 'in:info@arevantes.com']]);
        $service->authorize($request->user(), $data['id']);
        return back()->with('toast', ['type' => 'success', 'message' => 'Prueba autorizada; todavía no se ha enviado.']);
    }

    public function executeGraphTest(Request $request, string $id, GraphTechnicalTest $service): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:ENVIAR UNA VEZ'], 'recipient' => ['required', 'in:info@arevantes.com']]);
        abort_unless(\Illuminate\Support\Str::isUuid($id), 404);
        $status = $service->execute($request->user(), $id);
        return back()->with('toast', ['type' => $status === 'accepted' ? 'success' : 'error',
            'message' => $status === 'accepted' ? 'Microsoft aceptó el mensaje; la recepción debe comprobarse en el buzón.' : 'Resultado incierto: no volver a enviar.']);
    }

    public function confirmGraphTestReceipt(Request $request, string $id, GraphTechnicalTest $service): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:RECIBIDO']]);
        abort_unless(\Illuminate\Support\Str::isUuid($id), 404);
        $service->confirmReceipt($request->user(), $id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Recepción confirmada por el administrador.']);
    }
}
