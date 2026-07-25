<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseDocumentMail;
use App\Models\PurchaseDocument;
use App\Services\Documents\PurchaseDocumentPdf;
use App\Services\Documents\PurchaseWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;

class PurchaseDeliveryController extends Controller
{
    public function pdf(PurchaseDocument $purchaseDocument, PurchaseDocumentPdf $pdf): HttpResponse
    {
        abort_unless(in_array($purchaseDocument->type, ['purchase_rfq', 'purchase_order'], true), 422);

        return response($pdf->render($purchaseDocument), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$purchaseDocument->number.'.pdf"',
        ]);
    }

    public function send(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        abort_unless(in_array($purchaseDocument->type, ['purchase_rfq', 'purchase_order'], true), 422);
        $data = $request->validate([
            'recipient_email' => ['required', 'email:rfc', 'max:255'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        Mail::to($data['recipient_email'])->send(new PurchaseDocumentMail($purchaseDocument, $data['message'] ?? null));
        $workflow->markSent($purchaseDocument, $request->user(), $data['recipient_email']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase document sent to the supplier.')]);
    }
}
