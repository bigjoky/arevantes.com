<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodManager;
use App\Services\Accounting\AccountingWorkspace;
use App\Services\Accounting\CorporateTaxCalculator;
use App\Services\Accounting\JournalPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        FiscalYear::updateOrCreate(['year' => 2026], ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    private function lines(string $amount = '100.00'): array
    {
        return [['account_code' => '430', 'debit' => $amount, 'credit' => '0.00'], ['account_code' => '700', 'debit' => '0.00', 'credit' => $amount]];
    }

    private function draft(string $amount = '100.00'): JournalEntry
    {
        $this->post('/accounting/drafts', ['entry_date' => '2026-03-10', 'description' => 'Borrador real', 'lines' => $this->lines($amount)])->assertSessionHasNoErrors()->assertRedirect();

        return JournalEntry::where('status', 'draft')->latest('id')->firstOrFail();
    }

    public function test_draft_edit_post_and_retry_preserve_one_entry_and_exact_amounts(): void
    {
        $this->admin();
        $entry = $this->draft('0.10');
        $temporary = $entry->number;
        $this->assertSame('0.00', app(AccountingWorkspace::class)->summary('2026-01-01', '2026-12-31')['income']);
        $this->patch('/accounting/drafts/'.$entry->id, ['entry_date' => '2026-03-11', 'description' => 'Revisado', 'lines' => $this->lines('0.30')])->assertSessionHasNoErrors();
        $this->post('/accounting/drafts/'.$entry->id.'/post')->assertSessionHasNoErrors();
        $this->assertSame('posted', $entry->fresh()->status);
        $this->assertNotSame($temporary, $entry->fresh()->number);
        $this->assertSame('0.30', app(AccountingWorkspace::class)->summary('2026-01-01', '2026-12-31')['income']);
        $this->post('/accounting/drafts/'.$entry->id.'/post')->assertSessionHasErrors('entry');
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(2, $entry->lines()->count());
        $this->assertTrue(AuditLog::where('auditable_type', $entry->getMorphClass())->where('auditable_id', $entry->id)->where('event', 'updated')->exists());
    }

    public function test_unbalanced_drafts_cannot_post_and_closed_period_rejects_changes(): void
    {
        $this->admin();
        $lines = $this->lines();
        $lines[1]['credit'] = '90.00';
        $this->post('/accounting/drafts', ['entry_date' => '2026-03-10', 'description' => 'Pendiente', 'lines' => $lines])->assertSessionHasNoErrors();
        $entry = JournalEntry::latest('id')->firstOrFail();
        $this->post('/accounting/drafts/'.$entry->id.'/post')->assertSessionHasErrors('lines');
        $this->assertSame('draft', $entry->fresh()->status);
        AccountingPeriod::create(['fiscal_year_id' => $entry->fiscal_year_id, 'year' => 2026, 'month' => 3, 'status' => 'closed']);
        $this->patch('/accounting/drafts/'.$entry->id, ['entry_date' => '2026-03-11', 'description' => 'Cambio', 'lines' => $this->lines()])->assertSessionHasErrors('accounting_period');
        $this->assertSame('Pendiente', $entry->fresh()->description);
    }

    public function test_decimal_posting_rejects_negative_two_sided_and_rounds_exactly(): void
    {
        $user = $this->admin();
        $entry = app(JournalPoster::class)->post('2026-03-10', 'Decimal', $this->lines('0.105'), $user->id);
        $this->assertSame('0.11', $entry->lines()->first()->debit);
        foreach ([['account_code' => '430', 'debit' => '-1', 'credit' => '0'], ['account_code' => '430', 'debit' => '1', 'credit' => '1']] as $bad) {
            try {
                app(JournalPoster::class)->post('2026-03-10', 'Inválido', [$bad, $this->lines()[1]], $user->id);
                $this->fail('Expected rejection');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_posted_entries_cannot_be_edited_or_deleted_and_reversals_are_idempotent(): void
    {
        $user = $this->admin();
        $entry = app(JournalPoster::class)->post('2026-03-10', 'Original', $this->lines(), $user->id);
        $this->patch('/accounting/drafts/'.$entry->id, ['entry_date' => '2026-03-10', 'description' => 'Alteración', 'lines' => $this->lines()])->assertSessionHasErrors('entry');
        try {
            $entry->update(['description' => 'Alteración']);
            $this->fail('Expected immutable');
        } catch (ValidationException) {
        }
        $entry->refresh();
        try {
            $entry->delete();
            $this->fail('Expected immutable');
        } catch (ValidationException) {
        }
        $line = $entry->lines()->first();
        try {
            $line->update(['debit' => '99']);
            $this->fail('Expected immutable line');
        } catch (ValidationException) {
        }
        $this->post('/accounting/entries/'.$entry->id.'/reverse', ['entry_date' => '2026-03-11', 'description' => 'Corrección'])->assertSessionHasNoErrors();
        $this->post('/accounting/entries/'.$entry->id.'/reverse', ['entry_date' => '2026-03-11', 'description' => 'Duplicado'])->assertSessionHasErrors('entry');
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame('Original', $entry->fresh()->description);
        $this->assertSame('0.00', app(AccountingWorkspace::class)->summary('2026-01-01', '2026-12-31')['income']);
    }

    public function test_reports_exclude_drafts_and_closing_does_not_double_count_equity(): void
    {
        $user = $this->admin();
        app(JournalPoster::class)->post('2026-03-10', 'Venta', $this->lines(), $user->id);
        $this->draft('50');
        $summary = app(AccountingWorkspace::class)->summary('2026-01-01', '2026-12-31');
        $this->assertSame('100.00', $summary['assets'], json_encode($summary));
        $this->assertSame('0.00', $summary['equity']);
        $this->assertSame('100.00', $summary['result'], json_encode($summary));
        $this->assertSame('0.00', $summary['difference']);
        app(JournalPoster::class)->post('2026-12-31', 'Regularización', [['account_code' => '700', 'debit' => '100', 'credit' => '0'], ['account_code' => '129', 'debit' => '0', 'credit' => '100']], $user->id, 'closing');
        $summary = app(AccountingWorkspace::class)->summary('2026-01-01', '2026-12-31');
        $this->assertSame('100.00', $summary['equity'], json_encode($summary));
        $this->assertSame('100.00', $summary['result'], json_encode($summary));
        $this->assertSame('0.00', $summary['unclosedResult']);
        $this->assertSame('0.00', $summary['difference']);
        $this->get('/accounting?area=overview&year=2026')->assertOk()->assertInertia(fn (Assert $p) => $p->component('accounting/workspace')->where('summary.draftEntries', 1)->where('summary.assets', '100.00'));
    }

    public function test_backend_search_status_pagination_and_diary_lines(): void
    {
        $user = $this->admin();
        for ($i = 0; $i < 12; $i++) {
            app(JournalPoster::class)->post('2026-03-10', 'Venta '.$i, $this->lines('1'), $user->id);
        }
        $this->draft();
        $this->get('/accounting?area=entries&year=2026&entry_status=posted&page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->where('entries.total', 12)->has('entries.data', 2));
        $this->get('/accounting?area=entries&year=2026&search=Borrador')->assertOk()->assertInertia(fn (Assert $p) => $p->where('entries.total', 1)->where('entries.data.0.status', 'draft'));
        $this->get('/accounting?area=reports&view=journal&year=2026')->assertOk()->assertInertia(fn (Assert $p) => $p->where('entries.total', 12)->has('entries.data.0.lines', 2));
    }

    public function test_ledger_running_balance_crosses_pages_and_initial_balance_matches_trial(): void
    {
        $user = $this->admin();
        app(JournalPoster::class)->post('2026-01-10', 'Inicial', $this->lines('50'), $user->id);
        for ($i = 0; $i < 12; $i++) {
            app(JournalPoster::class)->post('2026-03-10', 'Venta '.$i, $this->lines('1'), $user->id);
        }
        $this->get('/accounting?area=reports&view=ledger&year=2026&account=430&from=2026-03-01&to=2026-03-31&page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->where('ledger.opening', '50.00')->where('ledger.lines.data.0.running', '61.00')->where('ledger.lines.data.1.running', '62.00'));
        $a = app(AccountingWorkspace::class)->accounts('2026-03-01', '2026-03-31', '2026-01-01')->where('code', '430')->first();
        $row = app(AccountingWorkspace::class)->accountRow($a);
        $this->assertSame('50.00', $row['opening']);
        $this->assertSame('12.00', $row['debit']);
        $this->assertSame('62.00', $row['net']);
    }

    public function test_read_only_permissions_and_company_guard_cover_reads_writes_and_exports(): void
    {
        $user = $this->admin();
        $entry = $this->draft();
        $user->permissionOverrides()->attach(Permission::where('key', 'manage-accounting')->firstOrFail(), ['allowed' => false]);
        $this->get('/accounting?area=overview')->assertOk();
        $this->post('/accounting/drafts', ['entry_date' => '2026-03-10'])->assertForbidden();
        $this->post('/accounting/drafts/'.$entry->id.'/post')->assertForbidden();
        CompanyProfile::create(['legal_name' => 'Segunda', 'tax_id' => 'B00000001']);
        foreach (['/accounting', '/accounting/accounts', '/accounting/export', '/accounting/entries/'.$entry->id, '/workspace/modules/accounting/books'] as $path) {
            $this->get($path)->assertStatus(409);
        }
    }

    public function test_books_do_not_claim_generation_and_csv_contains_only_filtered_posted_lines(): void
    {
        $user = $this->admin();
        app(JournalPoster::class)->post('2026-03-10', '=Venta', $this->lines('12.30'), $user->id);
        app(JournalPoster::class)->post('2026-03-11', 'Otra', $this->lines('5'), $user->id);
        $this->draft();
        $this->get('/workspace/modules/accounting/books?year=2026')->assertOk()->assertInertia(fn (Assert $p) => $p->where('area', 'books')->where('capabilities.books', false));
        $csv = $this->get('/accounting/export?year=2026&account=430&search=Venta')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Venta", $csv);
        $this->assertStringContainsString('12.30', $csv);
        $this->assertStringNotContainsString('Otra', $csv);
        $this->assertStringNotContainsString('Borrador', $csv);
        $this->get('/accounting/accounts?year=2026&search=430')->assertOk()->assertInertia(fn (Assert $p) => $p->component('accounting/workspace')->where('area', 'accounts'));
    }

    public function test_drafts_are_excluded_from_legacy_and_corporate_reports_and_block_month_close(): void
    {
        $user = $this->admin();
        app(JournalPoster::class)->post('2026-03-10', 'Venta', $this->lines('100'), $user->id);
        $draft = $this->draft('50');
        $year = FiscalYear::where('year', 2026)->firstOrFail();
        $this->assertSame(100.0, app(CorporateTaxCalculator::class)->accountingResult($year));
        $this->get('/accounting?area=management&year=2026')->assertOk()->assertInertia(fn (Assert $p) => $p->where('reports.profitAndLoss.income', 100));
        $this->assertGreaterThanOrEqual(1, app(AccountingPeriodManager::class)->summary(2026, 3)['unpostedItems']);
        $this->get('/accounting/entries/'.$draft->id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('accounting/entry')->where('entry.status', 'draft')->has('entry.lines', 2));
        $this->get('/accounting?year=2026&from=2025-12-01&to=2026-03-31')->assertStatus(422);
        $this->get('/accounting?year=2026&entry='.$draft->id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('accounting/entry'));
        $this->get('/accounting?year=2026&view=ledger&account=430')->assertOk()->assertInertia(fn (Assert $p) => $p->where('area', 'reports')->where('view', 'ledger'));
    }

    public function test_existing_service_can_post_large_entries_while_manual_draft_form_remains_bounded(): void
    {
        $user = $this->admin();
        $lines = [];
        for ($i = 0; $i < 26; $i++) {
            $lines = [...$lines, ...$this->lines('1.00')];
        }
        $entry = app(JournalPoster::class)->post('2026-03-10', 'Asiento de servicio con muchos apuntes', $lines, $user->id, 'automatic');
        $this->assertSame(52, $entry->lines()->count());
        $this->assertSame('26.00', app(AccountingWorkspace::class)->summary('2026-01-01', '2026-12-31')['income']);
        $this->post('/accounting/drafts', ['entry_date' => '2026-03-10', 'description' => 'Formulario acotado', 'lines' => $lines])->assertSessionHasErrors('lines');
        $this->assertSame(1, JournalEntry::count());
    }
}
