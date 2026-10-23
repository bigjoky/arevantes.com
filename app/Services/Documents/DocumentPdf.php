<?php

namespace App\Services\Documents;

use App\Models\CompanyProfile;
use App\Models\SalesDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class DocumentPdf
{
    public function render(object $document, string $kind, bool $final = false): string
    {
        if ($document instanceof SalesDocument && $document->locked_at && $document->archived_pdf_path) {
            $disk = Storage::disk(config('filesystems.private'));
            if (! $disk->exists($document->archived_pdf_path)) {
                throw new \RuntimeException('The archived invoice PDF is unavailable.');
            }
            $bytes = $disk->get($document->archived_pdf_path);
            if (! hash_equals($document->archived_pdf_hash, hash('sha256', $bytes))) {
                throw new \RuntimeException('The archived invoice PDF failed its integrity check.');
            }

            return $bytes;
        }
        $kind === 'quote'
            ? $document->loadMissing(['lines.components.unit', 'opportunity:id,code,title', 'installation:id,project_reference,site_name'])
            : $document->loadMissing('lines');
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $profile = CompanyProfile::first();
        $company = $document instanceof SalesDocument
            ? $document->issuerIdentity()
            : ($profile?->toArray() ?? config('company'));
        $company['logo_data'] = null;
        $company['logo_is_fallback'] = false;
        if ($profile?->logo_path && Storage::disk(config('filesystems.private'))->exists($profile->logo_path)) {
            $mime = Storage::disk(config('filesystems.private'))->mimeType($profile->logo_path) ?: 'image/png';
            $company['logo_data'] = 'data:'.$mime.';base64,'.base64_encode(Storage::disk(config('filesystems.private'))->get($profile->logo_path));
        } else {
            $logo = file_get_contents(public_path('brand/arevantes-logo-light.svg'));
            $company['logo_data'] = 'data:image/svg+xml;base64,'.base64_encode($logo);
            $company['logo_is_fallback'] = true;
        }
        $pdf->loadHtml(view($kind === 'quote' ? 'pdf.quote' : 'pdf.sales-document', [
            'document' => $document,
            'kind' => $kind,
            'company' => $company,
            'isDraft' => ! $final && isset($document->status) && $document->status === 'draft',
        ])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
