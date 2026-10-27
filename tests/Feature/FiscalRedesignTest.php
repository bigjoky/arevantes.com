<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanyCensusVersion;
use App\Models\CompanyProfile;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\SalesDocument;
use App\Models\TaxRate;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\CompanyCensus;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class FiscalRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    private function invoice(string $vat, array $extra = []): SalesDocument
    {
        return SalesDocument::create([...['number' => 'INV-'.str()->random(8), 'type' => 'invoice', 'status' => 'issued', 'issue_date' => '2026-08-01', 'subtotal' => '100.00', 'tax_amount' => $vat, 'amount' => '121.00', 'paid_amount' => '0.00', 'customer_snapshot' => ['company_name' => 'Cliente'], 'locked_at' => now()], ...$extra]);
    }

    private function declaration(array $extra = []): TaxReturn
    {
        return TaxReturn::create([...['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3, 'period_from' => '2026-07-01', 'period_to' => '2026-09-30', 'status' => 'draft', 'output_vat' => '21.00', 'input_vat' => '10.00', 'result' => '11.00', 'prepared_at' => now(), 'snapshot' => ['version' => 'historical-v1']], ...$extra]);
    }

    public function test_documentary_amounts_are_exact_signed_and_not_a_definitive_return(): void
    {
        $user = $this->admin();
        $invoice = $this->invoice('21.01');
        $this->invoice('1.02', ['type' => 'credit_note', 'source_document_id' => $invoice->id]);
        $this->invoice('100.00', ['status' => 'draft', 'locked_at' => null]);
        $this->invoice('100.00', ['status' => 'cancelled']);
        $this->invoice('100.00', ['issue_date' => '2025-08-01']);
        $this->invoice('100.00', ['issue_date' => '2026-10-01']);
        EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id, 'number' => 'EXP-EUR', 'type' => 'invoice', 'expense_date' => '2026-08-01', 'currency_code' => 'EUR', 'subtotal' => '100.00', 'tax_amount' => '21.03', 'withholding_amount' => '15.01', 'amount' => '106.02', 'status' => 'pending']);
        EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id, 'number' => 'EXP-USD', 'type' => 'invoice', 'expense_date' => '2026-08-01', 'currency_code' => 'USD', 'subtotal' => '100.00', 'tax_amount' => '99.00', 'amount' => '199.00', 'status' => 'pending']);
        $this->get('/fiscal?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $p) => $p->component('fiscal/workspace')->where('metrics.outputVat', '19.99')->where('metrics.supportedVat', '21.03')->where('metrics.withholding', '15.01')->where('metrics.result', null)->where('metrics.deductibleVat', null)->where('metrics.nextDeadline', null)->where('documents.total', 3)->where('excludedForeignExpenses', 1)->where('series.0.outputVat', '19.99')->where('capabilities.multiCompany', false));
    }

    public function test_unknown_census_never_activates_models_or_quarterly_periods(): void
    {
        $this->admin();
        $this->get('/fiscal/modelos?year=2026')->assertOk()->assertInertia(fn (Assert $p) => $p->where('modelCatalog.0.applicable', null)->where('modelCatalog.1.applicable', null)->where('periods', [])->where('census.current', false));
        $this->assertSame(0, CompanyCensusVersion::count());
    }

    public function test_periods_follow_reviewed_census_and_identity_change_invalidates_it(): void
    {
        $this->admin();
        $company = CompanyProfile::firstOrFail();
        FiscalYear::updateOrCreate(['year' => 2026], ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        CompanyCensusVersion::create(['company_profile_id' => $company->id, 'year' => 2026, 'status' => 'reviewed', 'settings' => ['model303' => 'yes', 'vat_regime' => 'general', 'periodicity' => 'quarterly', 'territory' => 'common', 'cash_accounting' => 'no', 'cash_recipient' => 'no', 'prorrata' => 'none', 'redeme' => 'no', 'sii' => 'no'], 'company_snapshot' => app(CompanyCensus::class)->identity($company), 'company_fingerprint' => app(CompanyCensus::class)->fingerprint($company), 'author_name' => 'Revisor', 'reason' => 'Comprobado', 'source_reference' => 'Referencia real de prueba']);
        $this->get('/fiscal/periodos?year=2026')->assertInertia(fn (Assert $p) => $p->has('periods', 4)->where('years.0.starts_on', '2026-01-01')->where('years.0.ends_on', '2026-12-31')->where('periods.0.deadline', null)->where('modelCatalog.0.applicable', true));
        $company->update(['legal_name' => 'Identidad cambiada']);
        $this->get('/fiscal/periodos?year=2026')->assertInertia(fn (Assert $p) => $p->where('periods', [])->where('modelCatalog.0.applicable', null));
    }

    public function test_filing_requires_matching_real_evidence_and_read_does_not_mutate_state(): void
    {
        Storage::fake('local');
        $this->admin();
        $return = $this->declaration(['status' => 'filed', 'filed_on' => '2026-10-10', 'filing_reference' => 'REAL-REF', 'receipt_path' => 'fiscal/test.pdf', 'receipt_sha256' => hash('sha256', 'receipt')]);
        $this->get('/fiscal/modelos?year=2026')->assertInertia(fn (Assert $p) => $p->where('declarations.data.0.status', 'evidence_pending')->where('declarations.data.0.receiptHref', null));
        Storage::disk('local')->put('fiscal/test.pdf', 'receipt');
        $this->get('/fiscal/modelos?year=2026')->assertInertia(fn (Assert $p) => $p->where('declarations.data.0.status', 'filed')->where('declarations.data.0.evidence', true));
        Storage::disk('local')->put('fiscal/test.pdf', 'changed');
        $this->get('/fiscal/declaraciones/303/'.$return->id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('fiscal/declaration')->where('declaration.evidence', false)->where('summary.calculationVersion', 'historical-v1'));
        $this->assertSame('filed', $return->fresh()->status);
        $this->assertSame(1, TaxReturn::count());
    }

    public function test_receipt_uses_configured_private_storage_and_requires_filing_metadata(): void
    {
        config(['filesystems.private' => 'fiscal-private-test']);
        Storage::fake('fiscal-private-test');
        $this->admin();
        $return = $this->declaration(['status' => 'filed', 'filed_on' => '2026-10-10', 'filing_reference' => 'REF-PRIVATE', 'receipt_path' => 'proof.pdf', 'receipt_sha256' => hash('sha256', 'proof')]);
        Storage::disk('fiscal-private-test')->put('proof.pdf', 'proof');
        $this->get('/fiscal/declaraciones/303/'.$return->id)->assertInertia(fn (Assert $p) => $p->where('declaration.evidence', true));
        $return->update(['filing_reference' => null]);
        $this->get('/fiscal/declaraciones/303/'.$return->id)->assertInertia(fn (Assert $p) => $p->where('declaration.evidence', false)->where('declaration.receiptHref', null));
    }

    public function test_read_only_finance_permission_cannot_create_or_reconfigure_taxes(): void
    {
        $profile = PermissionProfile::create(['name' => 'Consulta Fiscal', 'slug' => 'read-fiscal', 'is_system' => false]);
        $profile->permissions()->sync(Permission::where('key', 'view-finance')->pluck('id'));
        $this->actingAs(User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]));
        $this->get('/fiscal/impuestos')->assertOk()->assertInertia(fn (Assert $p) => $p->where('capabilities.manageTax', false)->where('capabilities.manageCensus', false)->where('capabilities.manageYears', false));
        $this->post('/fiscal/tax-rates', ['name' => 'Forbidden', 'rate' => '10', 'kind' => 'vat'])->assertForbidden();
        $this->get('/company-fiscal/census')->assertForbidden();
    }

    public function test_tax_rate_creation_is_append_only_audited_and_authorized(): void
    {
        $this->admin();
        $existing = TaxRate::firstOrFail();
        $original = $existing->getAttributes();
        $this->post('/fiscal/tax-rates', ['name' => 'Tipo nuevo', 'rate' => '7.25', 'kind' => 'vat'])->assertRedirect()->assertSessionHasNoErrors();
        $created = TaxRate::where('name', 'Tipo nuevo')->sole();
        $this->assertSame('7.25', $created->rate);
        $this->assertFalse($created->is_default);
        $this->assertSame($original, $existing->fresh()->getAttributes());
        $this->assertTrue(AuditLog::where('auditable_type', TaxRate::class)->where('auditable_id', $created->id)->where('event', 'created')->exists());
        $this->post('/fiscal/tax-rates', ['name' => 'Retención no integrada', 'rate' => '15', 'kind' => 'withholding'])->assertSessionHasErrors('kind');
        $this->post('/fiscal/tax-rates', ['name' => 'Inválido', 'rate' => '100.01', 'kind' => 'vat'])->assertSessionHasErrors('rate');
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->post('/fiscal/tax-rates', ['name' => 'Prohibido', 'rate' => '7.00', 'kind' => 'vat'])->assertForbidden();
        $this->assertFalse(TaxRate::where('name', 'Prohibido')->exists());
    }

    public function test_filters_and_pagination_are_backend_and_other_taxes_do_not_relabel_vat(): void
    {
        $this->admin();
        TaxRate::create(['name' => 'Tipo objetivo', 'rate' => '8.00', 'kind' => 'vat', 'is_active' => false]);
        $this->get('/fiscal/impuestos?search=Tipo%20objetivo&state=inactive')->assertInertia(fn (Assert $p) => $p->where('rates.total', 1)->where('rates.data.0.name', 'Tipo objetivo'));
        $this->get('/fiscal/impuestos?tab=other')->assertInertia(fn (Assert $p) => $p->where('rates.data', fn ($rows) => collect($rows)->every(fn ($r) => ! in_array($r['kind'], ['vat', 'withholding'], true))));
        for ($i = 0; $i < 12; $i++) {
            $this->invoice('0.01');
        }
        $this->get('/fiscal?year=2026&documents_page=2')->assertInertia(fn (Assert $p) => $p->where('documents.total', 12)->has('documents.data', 2)->where('metrics.outputVat', '0.12'));
        $this->get('/fiscal?year=1900')->assertSessionHasErrors('year');
    }

    public function test_routes_and_sidebar_respect_permissions_and_do_not_claim_multi_company(): void
    {
        $admin = $this->admin();
        $module = collect(app(WorkspaceNavigation::class)->for($admin))->firstWhere('id', 'tax');
        $this->assertSame('/fiscal', $module['href']);
        $this->assertTrue($module['navigable']);
        $this->assertSame(['/fiscal/impuestos', '/fiscal/modelos', '/fiscal/periodos'], array_column($module['children'], 'href'));
        foreach (['/fiscal', '/fiscal/impuestos', '/fiscal/modelos', '/fiscal/periodos'] as $url) {
            $this->get($url)->assertOk();
        }
        $profile = PermissionProfile::create(['name' => 'Sin Fiscal', 'slug' => 'without-fiscal', 'is_system' => false]);
        $commercial = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
        $this->actingAs($commercial);
        foreach (['/fiscal', '/fiscal/impuestos', '/fiscal/modelos', '/fiscal/periodos'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->assertNull(collect(app(WorkspaceNavigation::class)->for($commercial))->firstWhere('id', 'tax'));
        $this->actingAs($admin);
        CompanyProfile::create(['legal_name' => 'Segunda empresa', 'tax_id' => 'B00000001']);
        $this->get('/fiscal')->assertStatus(409);
        $this->post('/fiscal/tax-rates', ['name' => 'Scope unknown', 'rate' => '5', 'kind' => 'vat'])->assertStatus(409);
    }
}
