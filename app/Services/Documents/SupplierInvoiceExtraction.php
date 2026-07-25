<?php

namespace App\Services\Documents;

use App\Ai\Agents\SupplierInvoiceExtractor;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentAttachment;
use App\Models\PurchaseInboxDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image;
use Throwable;

class SupplierInvoiceExtraction
{
    public function extract(PurchaseDocumentAttachment|PurchaseInboxDocument $attachment): bool
    {
        $attachment->update(['extraction_status' => 'processing', 'extraction_error' => null]);
        $temporary = [];

        try {
            $attachments = $this->aiAttachments($attachment, $temporary);
            $data = SupplierInvoiceExtractor::make()->prompt(
                'Extract the supplier invoice fields from the attached document. Return only the requested structured fields.',
                attachments: $attachments,
                timeout: 120,
            )->toArray();
            $attachment->update([
                'extraction_status' => 'extracted',
                'extracted_data' => $this->normalize($data),
                'extracted_at' => now(),
                'verified_at' => null,
                'verified_by' => null,
            ]);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $attachment->update(['extraction_status' => 'failed', 'extraction_error' => __('Automatic extraction could not read this document.')]);

            return false;
        } finally {
            foreach ($temporary as $path) {
                File::delete($path);
            }
        }
    }

    public function comparison(PurchaseDocument $document): array
    {
        $data = $document->attachment?->extracted_data;
        if (! $data) {
            return [];
        }

        $supplier = $document->supplier;
        $fields = [
            ['field' => 'supplier_name', 'label' => __('Supplier'), 'extracted' => $data['supplier_name'] ?? '', 'registered' => $supplier?->legal_name ?? '', 'type' => 'text'],
            ['field' => 'supplier_tax_id', 'label' => __('Tax ID'), 'extracted' => $data['supplier_tax_id'] ?? '', 'registered' => $supplier?->tax_id ?? '', 'type' => 'text'],
            ['field' => 'invoice_number', 'label' => __('Invoice number'), 'extracted' => $data['invoice_number'] ?? '', 'registered' => $document->supplier_number ?? '', 'type' => 'text'],
            ['field' => 'issue_date', 'label' => __('Issue date'), 'extracted' => $data['issue_date'] ?? '', 'registered' => $document->issue_date?->toDateString() ?? '', 'type' => 'date'],
            ['field' => 'due_date', 'label' => __('Due date'), 'extracted' => $data['due_date'] ?? '', 'registered' => $document->due_date?->toDateString() ?? '', 'type' => 'date'],
            ['field' => 'subtotal', 'label' => __('Subtotal'), 'extracted' => $data['subtotal'] ?? null, 'registered' => (float) $document->subtotal, 'type' => 'amount'],
            ['field' => 'shipping_cost', 'label' => __('Transport'), 'extracted' => $data['shipping_cost'] ?? null, 'registered' => (float) $document->shipping_cost, 'type' => 'amount'],
            ['field' => 'tax_amount', 'label' => __('Tax'), 'extracted' => $data['tax_amount'] ?? null, 'registered' => (float) $document->tax_amount, 'type' => 'amount'],
            ['field' => 'total', 'label' => __('Total'), 'extracted' => $data['total'] ?? null, 'registered' => (float) $document->amount, 'type' => 'amount'],
        ];

        return collect($fields)->map(function (array $field) {
            $missing = $field['extracted'] === '' || $field['extracted'] === null;
            $matches = ! $missing && ($field['type'] === 'amount'
                ? abs((float) $field['extracted'] - (float) $field['registered']) < 0.01
                : mb_strtolower(trim((string) $field['extracted'])) === mb_strtolower(trim((string) $field['registered'])));

            return [...$field, 'status' => $missing ? 'missing' : ($matches ? 'matched' : 'different')];
        })->all();
    }

    private function aiAttachments(PurchaseDocumentAttachment|PurchaseInboxDocument $attachment, array &$temporary): array
    {
        if (str_starts_with($attachment->mime_type, 'image/')) {
            return [Image::fromStorage($attachment->path, 'local')];
        }

        $source = Storage::disk('local')->path($attachment->path);
        $directory = storage_path('app/private/purchase-extraction/'.$attachment->getTable().'-'.$attachment->id);
        File::ensureDirectoryExists($directory);
        $pdf = new \Imagick;
        $pdf->setResolution(150, 150);
        $pdf->readImage($source.'[0-2]');
        $images = [];
        foreach ($pdf as $index => $page) {
            $page->setImageFormat('png');
            $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $path = $directory.'/page-'.$index.'.png';
            $page->writeImage($path);
            $temporary[] = $path;
            $images[] = Image::fromPath($path, 'image/png');
        }
        $pdf->clear();

        return $images;
    }

    private function normalize(array $data): array
    {
        return [
            'supplier_name' => trim((string) ($data['supplier_name'] ?? '')),
            'supplier_tax_id' => strtoupper(trim((string) ($data['supplier_tax_id'] ?? ''))),
            'invoice_number' => trim((string) ($data['invoice_number'] ?? '')),
            'issue_date' => trim((string) ($data['issue_date'] ?? '')),
            'due_date' => trim((string) ($data['due_date'] ?? '')),
            'subtotal' => round((float) ($data['subtotal'] ?? 0), 2),
            'shipping_cost' => round((float) ($data['shipping_cost'] ?? 0), 2),
            'tax_amount' => round((float) ($data['tax_amount'] ?? 0), 2),
            'total' => round((float) ($data['total'] ?? 0), 2),
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'EUR'))),
            'confidence' => round(min(1, max(0, (float) ($data['confidence'] ?? 0))), 2),
        ];
    }
}
