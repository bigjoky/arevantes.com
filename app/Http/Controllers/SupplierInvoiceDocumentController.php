<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentAttachment;
use App\Rules\SafeUpload;
use App\Services\Documents\SupplierInvoiceExtraction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierInvoiceDocumentController extends Controller
{
    public function store(Request $request, PurchaseDocument $purchaseDocument): RedirectResponse
    {
        $this->assertSupported($purchaseDocument);
        abort_if($purchaseDocument->attachment()->exists(), 422, __('This document already has an original file.'));
        $file = $request->validate(['document_file' => ['required', 'file', new SafeUpload(['pdf', 'jpg', 'jpeg', 'png', 'webp'])]])['document_file'];
        $hash = hash_file('sha256', $file->getRealPath());
        if (PurchaseDocumentAttachment::where('file_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['document_file' => __('This file is already attached to another purchase document.')]);
        }

        $path = $file->store("purchase-documents/{$purchaseDocument->id}", config('filesystems.private'));
        try {
            $purchaseDocument->attachment()->create([
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'file_hash' => $hash,
                'extraction_status' => 'pending',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk(config('filesystems.private'))->delete($path);
            throw $exception;
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Original supplier document attached.')]);
    }

    public function download(PurchaseDocument $purchaseDocument): StreamedResponse
    {
        $this->assertSupported($purchaseDocument);
        $attachment = $purchaseDocument->attachment;
        abort_unless($attachment && Storage::disk(config('filesystems.private'))->exists($attachment->path), 404);

        return Storage::disk(config('filesystems.private'))->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    public function extract(PurchaseDocument $purchaseDocument, SupplierInvoiceExtraction $extraction): RedirectResponse
    {
        $this->assertSupported($purchaseDocument);
        $attachment = $purchaseDocument->attachment;
        abort_unless($attachment, 404);
        abort_if($attachment->extraction_status === 'verified', 422, __('Verified extraction cannot be run again.'));
        $success = $extraction->extract($attachment);

        return back()->with('toast', [
            'type' => $success ? 'success' : 'error',
            'message' => $success ? __('Document data extracted. Review the differences before verifying it.') : __('The document could not be extracted automatically.'),
        ]);
    }

    public function verify(Request $request, PurchaseDocument $purchaseDocument): RedirectResponse
    {
        $this->assertSupported($purchaseDocument);
        $attachment = $purchaseDocument->attachment;
        abort_unless($attachment && $attachment->extraction_status === 'extracted', 422, __('Extract the document before verifying it.'));
        $attachment->update(['extraction_status' => 'verified', 'verified_at' => now(), 'verified_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier document verified.')]);
    }

    private function assertSupported(PurchaseDocument $document): void
    {
        abort_unless(in_array($document->type, ['supplier_invoice', 'supplier_credit_note'], true), 422);
    }
}
