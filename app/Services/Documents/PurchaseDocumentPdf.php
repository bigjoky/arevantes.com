<?php

namespace App\Services\Documents;

use App\Models\CompanyProfile;
use App\Models\PurchaseDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class PurchaseDocumentPdf
{
    public function render(PurchaseDocument $document): string
    {
        $document->loadMissing(['supplier', 'destinationWarehouse', 'lines']);
        $profile = CompanyProfile::first();
        $company = $profile?->toArray() ?? config('company');
        $company['logo_data'] = null;
        if ($profile?->logo_path && Storage::disk('local')->exists($profile->logo_path)) {
            $mime = Storage::disk('local')->mimeType($profile->logo_path) ?: 'image/png';
            $company['logo_data'] = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($profile->logo_path));
        }

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.purchase-document', compact('document', 'company'))->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
