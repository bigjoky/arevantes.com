<?php

namespace App\Services\Documents;

use App\Models\CompanyProfile;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class DocumentPdf
{
    public function render(object $document, string $kind): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $profile = CompanyProfile::first();
        $company = $profile?->toArray() ?? config('company');
        $company['logo_data'] = null;
        if ($profile?->logo_path && Storage::disk('local')->exists($profile->logo_path)) {
            $mime = Storage::disk('local')->mimeType($profile->logo_path) ?: 'image/png';
            $company['logo_data'] = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($profile->logo_path));
        } else {
            $logo = file_get_contents(public_path('brand/arevantes-logo-light.svg'));
            $company['logo_data'] = 'data:image/svg+xml;base64,'.base64_encode($logo);
        }
        $pdf->loadHtml(view('pdf.sales-document', ['document' => $document, 'kind' => $kind, 'company' => $company])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
