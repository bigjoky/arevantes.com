<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Documents\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FiscalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_identity_and_document_series_are_protected_and_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('fiscal-settings.company.update'), [
            'legal_name' => 'Arevantes Energia SL', 'trade_name' => 'Arevantes', 'tax_id' => 'B12345678',
            'address' => 'Calle Energia 1', 'postal_code' => '14001', 'city' => 'Cordoba', 'province' => 'Cordoba',
            'country' => 'Spain', 'email' => 'finance@arevantes.com', 'iban' => 'ES9121000418450200051332',
        ])->assertRedirect();
        $this->assertSame('B12345678', CompanyProfile::firstOrFail()->tax_id);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => CompanyProfile::class, 'event' => 'updated']);

        $issued = app(DocumentNumber::class)->next('quote', now());
        $series = $issued['series']->fresh();
        $this->assertSame(2, $series->next_number);
        $this->patch(route('fiscal-settings.series.update', $series), ['name' => $series->name, 'prefix' => 'CHANGED', 'next_number' => 2, 'is_active' => true])->assertUnprocessable();
        $this->assertSame('PRE', $series->fresh()->prefix);
    }

    public function test_closed_fiscal_year_rejects_new_document_numbers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('fiscal-settings.years.store'), ['year' => 2027])->assertRedirect();
        $year = FiscalYear::where('year', 2027)->firstOrFail();
        $this->assertCount(11, $year->series);
        $this->post(route('fiscal-settings.years.close', $year))->assertRedirect();
        $this->assertSame('closed', $year->fresh()->status);

        $this->expectException(ValidationException::class);
        app(DocumentNumber::class)->next('invoice', '2027-03-01');
    }

    public function test_only_administrators_can_access_fiscal_configuration(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($commercial)->get(route('fiscal-settings.index'))->assertForbidden();
    }
}
