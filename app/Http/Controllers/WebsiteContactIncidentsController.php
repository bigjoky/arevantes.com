<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WebsiteContactReceipt;
use App\Services\Website\WebsiteContactReceipts;
use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('notifications/contact-incidents', ['receipts' => $receipts]);
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
}
