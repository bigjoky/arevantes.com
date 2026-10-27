<?php

namespace App\Services\Documents;

use App\Exceptions\IssuedDocumentUnavailable;
use App\Models\CompanyProfile;
use App\Models\SalesDocument;
use App\Support\CompanyBrand;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentPdf
{
    public function render(object $document, string $kind, bool $final = false): string
    {
        if ($document instanceof SalesDocument && $this->isIssued($document)) {
            $state = $this->custody($document);
            if ($state !== 'original_archived') {
                throw new IssuedDocumentUnavailable($state);
            }

            return $this->original($document);
        }

        return $this->generate($document, $kind, $final);
    }

    public function isIssued(SalesDocument $document): bool
    {
        return in_array($document->type, ['invoice', 'credit_note'], true)
            && ($document->locked_at || $document->issued_at || $document->status !== 'draft');
    }

    public function custody(SalesDocument $document): string
    {
        if (! $this->isIssued($document)) {
            return 'draft';
        }
        $disk = Storage::disk(config('filesystems.private'));
        if (! $document->archived_pdf_path || ! $disk->exists($document->archived_pdf_path)) {
            return 'historical_original_missing';
        }
        if (! is_string($document->archived_pdf_hash) || ! preg_match('/^[a-f0-9]{64}$/', $document->archived_pdf_hash)) {
            return 'integrity_failed';
        }

        return hash_equals($document->archived_pdf_hash, hash('sha256', $disk->get($document->archived_pdf_path)))
            ? 'original_archived' : 'integrity_failed';
    }

    private function original(SalesDocument $document): string
    {
        // Verify these exact bytes too: the file can change after the status check.
        $bytes = Storage::disk(config('filesystems.private'))->get($document->archived_pdf_path);
        if (! hash_equals($document->archived_pdf_hash, hash('sha256', $bytes))) {
            throw new IssuedDocumentUnavailable('integrity_failed');
        }

        return $bytes;
    }

    /** Only emission/correction services inside their transaction may create a definitive archive. */
    public function renderForArchiving(SalesDocument $document): string
    {
        if (! DB::transactionLevel() || ! $document->locked_at || ! $document->issuer_snapshot) {
            throw new \LogicException('Definitive rendering requires an issued snapshot inside its transaction.');
        }

        return $this->generate($document, $document->type, true);
    }

    public function reconstructInformative(SalesDocument $document): string
    {
        if ($this->custody($document) !== 'historical_original_missing') {
            throw new \LogicException('Informative reconstruction only applies to a missing historical original.');
        }

        return $this->generate($document, $document->type, true, true);
    }

    private function generate(object $document, string $kind, bool $final, bool $reconstruction = false): string
    {
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
        if ($reconstruction) {
            $company = ($document->issuer_snapshot ?: ['legal_name' => 'Emisor sin identidad histórica conservada']) + array_fill_keys(['tax_id', 'address', 'postal_code', 'city', 'province', 'email', 'phone'], null);
        }
        $company['logo_data'] = $reconstruction ? null : app(CompanyBrand::class)->dataUri($profile);
        $company['logo_is_fallback'] = false;
        $pdf->loadHtml(view($kind === 'quote' ? 'pdf.quote' : 'pdf.sales-document', [
            'document' => $document,
            'kind' => $kind,
            'company' => $company,
            'isReconstruction' => $reconstruction,
            'isDraft' => ! $final && isset($document->status) && $document->status === 'draft',
        ])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
