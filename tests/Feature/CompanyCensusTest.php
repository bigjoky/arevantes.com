<?php

namespace Tests\Feature;

use App\Models\CompanyCensusVersion;
use App\Models\CompanyProfile;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\CompanyCensus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyCensusTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): CompanyProfile
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $company = CompanyProfile::firstOrFail();
        $company->update(['legal_name' => 'Test Company SA', 'tax_id' => 'A12345678', 'address' => 'Test street 1',
            'postal_code' => '28001', 'city' => 'Madrid', 'province' => 'Madrid', 'country' => 'ES']);
        FiscalYear::updateOrCreate(['year' => 2026], ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);

        return $company->fresh();
    }

    private function data(CompanyProfile $company): array
    {
        return ['year' => 2026, 'revision' => app(CompanyCensus::class)->revision($company->fresh(), 2026), 'action' => 'review',
            'reason' => 'Census reference checked with adviser', 'source_reference' => 'Census certificate dated 2026-01-01', 'confirmed' => true,
            'settings' => ['model303' => 'yes', 'vat_regime' => 'general', 'periodicity' => 'quarterly', 'territory' => 'common',
                'cash_accounting' => 'no', 'cash_recipient' => 'no', 'prorrata' => 'none', 'redeme' => 'no', 'sii' => 'no']];
    }

    public function test_reading_does_not_assume_or_create_a_census_profile(): void
    {
        $this->prepare();
        $this->get('/company-fiscal/census?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tax/company-census')->where('check.current', false)->where('check.supported', false)->has('history', 0));
        $this->assertSame(0, CompanyCensusVersion::count());
    }

    public function test_reviewed_version_is_audited_without_changing_company_or_returns(): void
    {
        $company = $this->prepare();
        $before = $company->attributesToArray();
        $this->post('/company-fiscal/census', $this->data($company))->assertRedirect()->assertSessionHasNoErrors();
        $version = CompanyCensusVersion::sole();
        $this->assertSame('reviewed', $version->status);
        $this->assertSame(auth()->user()->name, $version->author_name);
        $this->assertSame('A12345678', $version->company_snapshot['tax_id']);
        $check = app(CompanyCensus::class)->check($company->fresh(), 2026);
        $this->assertTrue($check['current']);
        $this->assertTrue($check['supported']);
        $version->update(['settings' => array_reverse($version->settings, true)]);
        $this->assertTrue(app(CompanyCensus::class)->check($company->fresh(), 2026)['supported']);
        $this->assertFalse(app(CompanyCensus::class)->check($company, 2027)['current']);
        $this->assertSame($before, $company->fresh()->attributesToArray());
        $this->assertSame(0, TaxReturn::count());
        $this->assertSame(0, JournalEntry::count());
        $this->get('/tax/models/303?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('census.current', true)->where('census.supported', true)->where('preview.ready', false));
    }

    public function test_unknown_settings_may_be_saved_as_draft_but_not_reviewed(): void
    {
        $company = $this->prepare();
        $data = $this->data($company);
        $data['settings']['model303'] = 'unknown';
        $this->post('/company-fiscal/census', $data)->assertSessionHasErrors('settings');
        $this->post('/company-fiscal/census', [...$data, 'action' => 'draft', 'confirmed' => false])->assertSessionHasNoErrors();
        $this->assertSame('draft', CompanyCensusVersion::sole()->status);
        $this->assertFalse(app(CompanyCensus::class)->check($company, 2026)['current']);
    }

    public function test_requires_evidence_confirmation_and_complete_identity_for_review(): void
    {
        $company = $this->prepare();
        $this->post('/company-fiscal/census', [...$this->data($company), 'confirmed' => false])->assertSessionHasErrors('settings');
        $this->post('/company-fiscal/census', [...$this->data($company), 'source_reference' => ''])->assertSessionHasErrors('settings');
        $company->update(['tax_id' => null]);
        $this->post('/company-fiscal/census', $this->data($company))->assertSessionHasErrors('settings');
        $this->assertSame(0, CompanyCensusVersion::count());
    }

    public function test_stale_versions_and_identity_changes_do_not_overwrite_history(): void
    {
        $company = $this->prepare();
        $stale = $this->data($company);
        $this->post('/company-fiscal/census', $stale)->assertSessionHasNoErrors();
        $this->post('/company-fiscal/census', $stale)->assertSessionHasErrors('settings');
        $company->update(['email' => 'other@example.test']);
        $this->assertTrue(app(CompanyCensus::class)->check($company->fresh(), 2026)['current']);
        $stale = $this->data($company);
        $company->update(['legal_name' => 'Changed legal identity']);
        $this->assertFalse(app(CompanyCensus::class)->check($company->fresh(), 2026)['current']);
        $this->post('/company-fiscal/census', $stale)->assertSessionHasErrors('settings');
        $this->post('/company-fiscal/census', $this->data($company))->assertSessionHasNoErrors();
        $this->post('/company-fiscal/census', [...$this->data($company), 'action' => 'draft'])->assertSessionHasNoErrors();
        $this->assertSame(3, CompanyCensusVersion::count());
        $this->assertFalse(app(CompanyCensus::class)->check($company->fresh(), 2026)['current']);
        $this->assertSame('Test Company SA', CompanyCensusVersion::oldest('id')->first()->company_snapshot['legal_name']);
    }

    public function test_monthly_profile_is_reviewed_but_outside_current_303_scope(): void
    {
        $company = $this->prepare();
        $data = $this->data($company);
        $data['settings']['periodicity'] = 'monthly';
        $data['settings']['redeme'] = 'yes';
        $this->post('/company-fiscal/census', $data)->assertSessionHasNoErrors();
        $check = app(CompanyCensus::class)->check($company, 2026);
        $this->assertTrue($check['current']);
        $this->assertFalse($check['supported']);
    }

    public function test_closed_year_and_prepared_vat_return_protect_versions(): void
    {
        $company = $this->prepare();
        FiscalYear::where('year', 2026)->update(['status' => 'closed']);
        $this->post('/company-fiscal/census', $this->data($company))->assertSessionHasErrors('settings');
        FiscalYear::where('year', 2026)->update(['status' => 'open']);
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3, 'period_from' => '2026-07-01', 'period_to' => '2026-09-30',
            'status' => 'prepared', 'result' => 0, 'output_vat' => 0, 'input_vat' => 0, 'snapshot' => [], 'prepared_at' => now()]);
        $this->post('/company-fiscal/census', $this->data($company))->assertSessionHasErrors('settings');
        $this->assertSame(0, CompanyCensusVersion::count());
        $this->get('/company-fiscal/census?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page->has('protection', 1));
    }

    public function test_admin_only_and_only_declared_select_options_are_accepted(): void
    {
        $company = $this->prepare();
        $data = $this->data($company);
        $data['settings']['territory'] = 'invented';
        $this->post('/company-fiscal/census', $data)->assertSessionHasErrors('settings.territory');
        $data = $this->data($company);
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->get('/company-fiscal/census')->assertForbidden();
        $this->post('/company-fiscal/census', $data)->assertForbidden();
        $this->assertSame(0, CompanyCensusVersion::count());
    }
}
