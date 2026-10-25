<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Services\Documents\DocumentPdf;
use App\Services\Documents\PurchaseDocumentPdf;
use App\Support\CompanyBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyBrandPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_and_purchase_documents_use_the_original_logo_when_no_custom_resource_exists(): void
    {
        CompanyProfile::firstOrFail()->update(['logo_path' => null]);
        $supplier = Supplier::create(['legal_name' => 'Proveedor de prueba', 'country' => 'ES']);
        $purchase = PurchaseDocument::create(['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'number' => 'BRAND-PURCHASE', 'status' => 'draft', 'issue_date' => '2026-10-25', 'subtotal' => 0, 'tax_amount' => 0, 'amount' => 0]);
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'BRAND-SALE', 'status' => 'draft', 'issue_date' => '2026-10-25', 'subtotal' => 0, 'tax_amount' => 0, 'amount' => 0, 'customer_snapshot' => []]);
        $this->assertStringStartsWith('%PDF', app(PurchaseDocumentPdf::class)->render($purchase));
        $this->assertStringStartsWith('%PDF', app(DocumentPdf::class)->render($sale, 'invoice'));
        $logo = app(CompanyBrand::class)->dataUri(null);
        $company = CompanyProfile::firstOrFail()->toArray();
        $company['logo_data'] = null;
        foreach ([
            ['pdf.sales-document', ['document' => $sale, 'company' => $company, 'kind' => 'invoice']],
            ['pdf.purchase-document', ['document' => $purchase, 'company' => $company, 'supplierIdentity' => $purchase->supplierIdentity()]],
        ] as [$template, $data]) {
            $html = view($template, $data)->render();
            $this->assertStringContainsString($logo, $html);
            $this->assertStringNotContainsString('<span class="brand">', $html);
        }
    }
}
