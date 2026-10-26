<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\FiscalYear;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\OpportunityNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CommercialNumberReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26));
    }

    private function reserve(string $type, int $number, int $year = 2026): void
    {
        DB::table('commercial_number_reservations')->insert([
            'document_type' => $type, 'year' => $year, 'number' => $number,
            'reason' => 'Duplicate permanently removed by an authorized operator.', 'created_at' => now(),
        ]);
    }

    public function test_opportunity_reuses_only_the_reserved_gap_then_continues_after_existing_numbers(): void
    {
        $existing = Opportunity::create(['code' => 'OPP-2026-00014', 'title' => 'Existing', 'stage' => 'new']);
        $this->reserve('opportunity', 10);

        // Internal IDs continue upwards; business codes consume the reservation.
        foreach (['OPP-2026-00010', 'OPP-2026-00015', 'OPP-2026-00016'] as $expected) {
            $opportunity = DB::transaction(function () {
                $opportunity = Opportunity::create(['title' => 'Real opportunity', 'stage' => 'new']);
                $opportunity->update(['code' => app(OpportunityNumber::class)->next($opportunity->id)]);

                return $opportunity;
            });
            $this->assertGreaterThan($existing->id, $opportunity->id);
            $this->assertSame($expected, $opportunity->fresh()->code);
        }

        $this->assertDatabaseMissing('commercial_number_reservations', ['consumed_at' => null, 'document_type' => 'opportunity']);
        $this->assertDatabaseMissing('opportunities', ['code' => 'OPP-2026-00009']);
    }

    public function test_quote_reuses_six_without_advancing_the_normal_series_then_issues_eight_and_nine(): void
    {
        $year = FiscalYear::firstOrCreate(['year' => 2026], ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $series = DocumentSeries::updateOrCreate(['document_type' => 'quote', 'fiscal_year_id' => $year->id], ['name' => 'Quotes 2026', 'prefix' => 'PRE', 'fiscal_year' => 2026, 'fiscal_year_id' => $year->id, 'next_number' => 8, 'is_active' => true]);
        $this->reserve('quote', 6);

        foreach (['PRE-2026-00006' => 8, 'PRE-2026-00008' => 9, 'PRE-2026-00009' => 10] as $expected => $next) {
            DB::transaction(function () use ($expected): void {
                $issued = app(DocumentNumber::class)->next('quote', '2026-10-26');
                $this->assertSame($expected, $issued['number']);
                Quote::create(['number' => $issued['number'], 'document_series_id' => $issued['series']->id, 'status' => 'draft', 'amount' => 0]);
            });
            $this->assertSame($next, (int) $series->fresh()->next_number);
        }
    }

    public function test_failed_creation_rolls_back_reservation_consumption(): void
    {
        $this->reserve('opportunity', 10);
        try {
            DB::transaction(function (): void {
                $this->assertSame('OPP-2026-00010', app(OpportunityNumber::class)->next(15));
                throw new RuntimeException('Creation failed');
            });
        } catch (RuntimeException) {
            // The next successful creation must still receive the reserved code.
        }

        $this->assertDatabaseHas('commercial_number_reservations', ['document_type' => 'opportunity', 'number' => 10, 'consumed_at' => null]);
        $this->assertSame('OPP-2026-00010', DB::transaction(fn () => app(OpportunityNumber::class)->next(15)));
        $this->assertSame('OPP-2026-00015', DB::transaction(fn () => app(OpportunityNumber::class)->next(16)));
    }

    public function test_an_archived_opportunity_number_cannot_be_reused(): void
    {
        $opportunity = Opportunity::create(['code' => 'OPP-2026-00010', 'title' => 'Archived', 'stage' => 'new']);
        $opportunity->delete();
        $this->reserve('opportunity', 10);

        $this->expectException(LogicException::class);
        DB::transaction(fn () => app(OpportunityNumber::class)->next(15));
    }

    public function test_reservations_are_scoped_by_document_type_and_year(): void
    {
        $this->reserve('quote', 6);
        $this->reserve('opportunity', 9, 2025);

        $this->assertSame('OPP-2026-00015', DB::transaction(fn () => app(OpportunityNumber::class)->next(15)));
        $this->assertSame(2, DB::table('commercial_number_reservations')->whereNull('consumed_at')->count());
    }
}
