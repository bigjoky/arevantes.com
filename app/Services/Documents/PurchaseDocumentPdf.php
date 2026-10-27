<?php

namespace App\Services\Documents;

use App\Models\CompanyProfile;
use App\Models\PurchaseDocument;
use App\Support\CompanyBrand;
use Dompdf\Dompdf;
use Dompdf\Options;

class PurchaseDocumentPdf
{
    public function render(PurchaseDocument $document): string
    {
        $document->loadMissing(['supplier', 'destinationWarehouse', 'lines']);
        $supplierIdentity = $document->supplierIdentity();
        $profile = CompanyProfile::first();
        $company = $profile?->toArray() ?? config('company');
        $company['logo_data'] = app(CompanyBrand::class)->dataUri($profile);
        $company['logo_is_fallback'] = false;
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.purchase-document', compact('document', 'company', 'supplierIdentity'))->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
