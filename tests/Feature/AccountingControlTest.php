<?php

namespace Tests\Feature;

use App\Models\AccountingAccountAssignment;
use App\Models\AccountingConfiguration;
use App\Models\AccountingEntryCorrection;
use App\Models\AccountingPeriod;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Accounting\AccountingAccountPolicy;
use App\Services\Accounting\AccountingOperationInput;
use App\Services\Accounting\AccountingReview;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalConcepts;
use App\Services\Accounting\JournalCorrection;
use App\Services\Accounting\JournalPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class AccountingControlTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin']);
        $this->actingAs($u);

        return $u;
    }

    private function account(string $code, string $type = 'asset'): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(['code' => $code], ['name' => 'SYN '.$code, 'type' => $type, 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
    }

    public function test_identity_save_does_not_create_or_reactivate_customer_accounts(): void
    {
        $this->admin();
        $before = LedgerAccount::count();
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic', 'last_name' => 'Customer']);
        $this->assertNull($p->fresh()->receivable_account_code);
        $this->assertSame($before, LedgerAccount::count());
    }

    public function test_assignment_is_explicit_validated_and_future_only(): void
    {
        $this->admin();
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $a = $this->account('4309001');
        $url = route('accounting.control.assignments', ['kind' => 'customer', 'id' => $p->id]);
        $revision = $this->getJson($url)->assertOk()->json('revision');
        $this->patch($url, ['role' => 'receivable', 'account_code' => $a->code, 'revision' => $revision, 'reason' => 'Synthetic explicit decision', 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame($a->code, $p->fresh()->receivable_account_code);
        $this->assertSame('specific', app(AccountingAccountPolicy::class)->decision('receivable', specific: $p->fresh(), legacySpecific: $a->code, fallback: '430')['origin']);
        $revision = $this->getJson($url)->json('revision');
        $this->patch($url, ['role' => 'receivable', 'account_code' => '700', 'revision' => $revision, 'reason' => 'Invalid synthetic account', 'confirmed' => true])->assertSessionHasErrors('accounting');
    }

    public function test_priority_is_operation_specific_rule_category_then_general(): void
    {
        $this->admin();
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $a = $this->account('4309002');
        AccountingConfiguration::create(['id' => 1, 'defaults' => ['receivable' => $a->code], 'rules' => [], 'templates' => []]);
        $decision = app(AccountingAccountPolicy::class)->decision('receivable', fallback: '430');
        $this->assertSame($a->code, $decision['code']);
        $this->assertSame('system', $decision['origin']);
        $this->assertSame('430', app(AccountingAccountPolicy::class)->code('receivable', specific: $p, legacySpecific: '430', fallback: '430'));
    }

    public function test_posted_account_correction_retains_original_and_is_idempotent(): void
    {
        $u = $this->admin();
        $a = $this->account('6299001', 'expense');
        $entry = app(JournalPoster::class)->post('2026-09-21', 'Synthetic original', [['account_code' => '629', 'debit' => 100, 'credit' => 0], ['account_code' => '572', 'debit' => 0, 'credit' => 100]], $u->id);
        $before = $entry->fresh()->getAttributes();
        $line = $entry->lines()->with('account')->whereHas('account', fn ($q) => $q->where('code', '629'))->firstOrFail();
        $data = ['entry_date' => '2026-09-21', 'description' => 'Synthetic annotated concept', 'notes' => 'Synthetic notes', 'reference' => 'SYN-REF', 'reason' => 'Synthetic incorrect account', 'operation_key' => (string) Str::uuid(), 'revision' => app(JournalCorrection::class)->revision($entry), 'lines' => [['id' => $line->id, 'account_code' => $a->code, 'description' => 'Synthetic corrected line']], 'confirmed' => true];
        $url = route('accounting.corrections.store', $entry);
        $this->post($url, $data)->assertRedirect(route('accounting.entries.show', $entry))->assertSessionHasNoErrors();
        $this->post($url, $data)->assertRedirect(route('accounting.entries.show', $entry))->assertSessionHasNoErrors();
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertSame('629', $line->fresh()->account->code);
        $this->assertDatabaseCount('accounting_entry_corrections', 1);
        $this->assertDatabaseCount('journal_entries', 2);
        $c = AccountingEntryCorrection::firstOrFail();
        $adjustment = JournalEntry::findOrFail($c->correction_entry_id);
        $this->assertSame('correction', $adjustment->entry_kind);
        app(JournalPoster::class)->post('2026-09-21', 'Synthetic cancellation', $entry->lines()->with('account')->get()->map(fn ($l) => ['account_code' => $l->account->code, 'debit' => $l->credit, 'credit' => $l->debit])->all(), $u->id, 'reversal', reverses: $entry);
        $this->assertNotNull($adjustment->fresh()->reversal);
        $this->assertSame($before, $entry->fresh()->getAttributes());
    }

    public function test_descriptive_annotation_does_not_create_economic_entry(): void
    {
        $u = $this->admin();
        $entry = app(JournalPoster::class)->post('2026-09-21', 'Synthetic original', [['account_code' => '629', 'debit' => 100, 'credit' => 0], ['account_code' => '572', 'debit' => 0, 'credit' => 100]], $u->id);
        $this->post(route('accounting.corrections.store', $entry), ['entry_date' => '2026-09-21', 'description' => 'Synthetic annotation', 'reason' => 'Synthetic descriptive correction', 'operation_key' => (string) Str::uuid(), 'revision' => app(JournalCorrection::class)->revision($entry), 'lines' => [], 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame('Synthetic original', $entry->fresh()->description);
        $this->assertNull(AccountingEntryCorrection::firstOrFail()->correction_entry_id);
    }

    public function test_concepts_do_not_invent_unavailable_document_references(): void
    {
        $this->assertSame('Anticipo cliente — Synthetic', app(JournalConcepts::class)->render('customer_advance', ['cliente' => 'Synthetic'], 'es'));
        $this->assertStringNotContainsString('{numero}', app(JournalConcepts::class)->render('sales_invoice', [], 'es'));
    }

    public function test_future_customer_mapping_does_not_move_old_invoice_or_its_receipt(): void
    {
        $u = $this->admin();
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic frozen customer']);
        $old = $this->account('4309101');
        $new = $this->account('4309102');
        $p->update(['receivable_account_code' => $old->code]);
        $doc = SalesDocument::create(['type' => 'invoice', 'person_id' => $p->id, 'number' => 'SYN-FROZEN', 'status' => 'issued', 'issue_date' => '2026-09-21', 'subtotal' => 100, 'amount' => 100, 'tax_amount' => 0, 'paid_amount' => 0, 'customer_snapshot' => []]);
        $sync = app(AccountingSynchronizer::class);
        $sync->syncSalesDocument($doc, $u->id);
        $original = $doc->accountingEntry->load('lines')->toArray();
        $p->update(['receivable_account_code' => $new->code]);
        $receipt = $doc->payments()->create(['paid_at' => '2026-09-22', 'amount' => 100, 'method' => 'bank_transfer']);
        $sync->syncCustomerPayment($receipt, $u->id);
        $this->assertSame('100.00', $receipt->accountingEntry->lines()->where('ledger_account_id', $old->id)->firstOrFail()->credit);
        $this->assertSame($original, $doc->accountingEntry->fresh()->load('lines')->toArray());
        $fresh = SalesDocument::create(['type' => 'invoice', 'person_id' => $p->id, 'number' => 'SYN-NEW', 'status' => 'issued', 'issue_date' => '2026-09-23', 'subtotal' => 100, 'amount' => 100, 'tax_amount' => 0, 'paid_amount' => 0, 'customer_snapshot' => []]);
        $sync->syncSalesDocument($fresh, $u->id);
        $this->assertSame('100.00', $fresh->accountingEntry->lines()->where('ledger_account_id', $new->id)->firstOrFail()->debit);
    }

    public function test_all_five_assignment_priorities_are_deterministic(): void
    {
        $this->admin();
        $category = ExpenseCategory::firstOrFail();
        $category->update(['expense_account_code' => '623']);
        $expense = EconomicFixture::expense(['expense_category_id' => $category->id, 'number' => 'SYN-RULE', 'type' => 'receipt', 'expense_date' => '2026-09-21', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 0, 'status' => 'pending']);
        $config = AccountingConfiguration::create(['id' => 1, 'defaults' => ['expense' => '629'], 'rules' => [['role' => 'expense', 'account_code' => '628', 'active' => true]], 'templates' => []]);
        $policy = app(AccountingAccountPolicy::class);
        $this->assertSame('628', $policy->code('expense', $expense, null, $category, categoryCode: '623', fallback: '629'));
        $specific = $this->account('6299101', 'expense');
        AccountingAccountAssignment::create(['entity_type' => $category->getMorphClass(), 'entity_id' => $category->id, 'role' => 'expense', 'ledger_account_id' => $specific->id]);
        $this->assertSame('specific', $policy->decision('expense', $expense, $category, $category, legacySpecific: '623', categoryCode: '623', fallback: '629')['origin']);
        $manual = $this->account('6299102', 'expense');
        AccountingAccountAssignment::create(['entity_type' => $expense->getMorphClass(), 'entity_id' => $expense->id, 'role' => 'expense', 'ledger_account_id' => $manual->id]);
        $this->assertSame($manual->code, $policy->code('expense', $expense, $category, $category, legacySpecific: '623', categoryCode: '623', fallback: '629'));
        AccountingAccountAssignment::where('entity_type', $expense->getMorphClass())->delete();
        $config->update(['rules' => []]);
        $this->assertSame('category', $policy->decision('expense', $expense, null, $category, categoryCode: '623', fallback: '629')['origin']);
        $this->assertSame('system', $policy->decision('expense', fallback: '629')['origin']);
    }

    public function test_configuration_is_versioned_and_rejects_incompatible_accounts_and_placeholders(): void
    {
        $this->admin();
        $data = ['revision' => 0, 'defaults' => ['receivable' => '700'], 'rules' => [], 'templates' => JournalConcepts::DEFAULTS, 'reason' => 'Synthetic reviewed configuration', 'confirmed' => true];
        $this->patch(route('accounting.control.save'), $data)->assertSessionHasErrors('accounting');
        $data['defaults'] = ['receivable' => '430'];
        $data['templates']['receipt']['es'] = '{unsupported}';
        $this->patch(route('accounting.control.save'), $data)->assertSessionHasErrors('templates');
        $data['templates'] = JournalConcepts::DEFAULTS;
        $this->patch(route('accounting.control.save'), $data)->assertSessionHasNoErrors();
        $this->patch(route('accounting.control.save'), $data)->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('accounting_configurations', 1);
    }

    public function test_closed_period_permissions_and_mismatched_lines_prevent_corrections(): void
    {
        $u = $this->admin();
        $entry = app(JournalPoster::class)->post('2026-09-21', 'Synthetic original', [['account_code' => '629', 'debit' => 100, 'credit' => 0], ['account_code' => '572', 'debit' => 0, 'credit' => 100]], $u->id);
        $data = ['entry_date' => '2026-09-21', 'description' => 'Synthetic annotation', 'reason' => 'Synthetic reason', 'operation_key' => (string) Str::uuid(), 'revision' => app(JournalCorrection::class)->revision($entry), 'lines' => [], 'confirmed' => true];
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->post(route('accounting.corrections.store', $entry), $data)->assertForbidden();
        $this->actingAs($u);
        AccountingPeriod::create(['fiscal_year_id' => $entry->fiscal_year_id, 'year' => 2026, 'month' => 9, 'status' => 'closed']);
        $this->post(route('accounting.corrections.store', $entry), $data)->assertSessionHasErrors('accounting_period');
        $this->assertDatabaseCount('accounting_entry_corrections', 0);
        AccountingPeriod::query()->delete();
        $data['lines'] = [['id' => 999999, 'account_code' => '629', 'description' => 'Synthetic']];
        $this->post(route('accounting.corrections.store', $entry), $data)->assertSessionHasErrors('correction');
        $data['lines'] = [['id' => $entry->lines()->first()->id, 'account_code' => '700', 'description' => 'Synthetic']];
        $this->post(route('accounting.corrections.store', $entry), $data)->assertSessionHasErrors('correction');
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_review_queries_and_entry_detail_are_operational_read_only(): void
    {
        $this->admin();
        Person::create(['category' => 'customer', 'first_name' => 'Synthetic review']);
        foreach (AccountingReview::ISSUES as $issue) {
            $this->get(route('accounting.review', ['issue' => $issue]))->assertOk();
        }
        $this->get(route('accounting.control'))->assertOk();
        $this->assertDatabaseCount('accounting_configurations', 0);
    }

    public function test_chart_creation_duplicates_and_used_account_protection(): void
    {
        $u = $this->admin();
        $data = ['code' => '6299201', 'name' => 'Synthetic manual account', 'type' => 'expense', 'parent_code' => '629', 'is_active' => true, 'is_postable' => true];
        $this->post(route('accounting.accounts.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('accounting.accounts.store'), $data)->assertSessionHasErrors('code');
        $a = LedgerAccount::where('code', $data['code'])->firstOrFail();
        app(JournalPoster::class)->post('2026-09-21', 'Synthetic used account', [['account_code' => $a->code, 'debit' => 10, 'credit' => 0], ['account_code' => '572', 'debit' => 0, 'credit' => 10]], $u->id);
        $this->patch(route('accounting.accounts.update', $a), [...$data, 'code' => '6299999'])->assertSessionHasErrors('code');
        $this->delete(route('accounting.accounts.destroy', $a))->assertSessionHasErrors('account');
        $this->get(route('accounting.accounts.show', $a))->assertOk();
    }

    public function test_journal_search_covers_the_full_year_and_line_concepts_beyond_250_entries(): void
    {
        $u = $this->admin();
        $year = FiscalYear::where('year', 2026)->firstOrFail();
        $rows = [];
        for ($i = 1; $i <= 270; $i++) {
            $rows[] = ['fiscal_year_id' => $year->id, 'created_by' => $u->id, 'entry_date' => '2026-09-21', 'number' => 'SYN-DIARY-'.$i, 'description' => 'Synthetic historical journal', 'event' => 'manual', 'entry_kind' => 'manual', 'status' => 'posted'];
        }
        DB::table('journal_entries')->insert($rows);
        $ids = JournalEntry::orderBy('id')->pluck('id');
        $lines = [];
        foreach ($ids as $i => $id) {
            $lines[] = ['journal_entry_id' => $id, 'ledger_account_id' => LedgerAccount::where('code', '629')->value('id'), 'description' => $i === 269 ? 'Synthetic unique line 270' : 'Synthetic expense', 'debit' => 1, 'credit' => 0];
            $lines[] = ['journal_entry_id' => $id, 'ledger_account_id' => LedgerAccount::where('code', '572')->value('id'), 'description' => 'Synthetic funding', 'debit' => 0, 'credit' => 1];
        }DB::table('journal_lines')->insert($lines);
        $csv = $this->get('/accounting/export?year=2026&concept=Synthetic%20unique%20line%20270')->assertOk()->streamedContent();
        $this->assertStringContainsString('SYN-DIARY-270', $csv);
        $this->assertStringNotContainsString('SYN-DIARY-269', $csv);
        $this->get('/accounting?year=2026&area=entries&search=Synthetic%20unique%20line%20270')->assertInertia(fn (AssertableInertia $p) => $p->where('entries.total', 1)->where('entries.data.0.number', 'SYN-DIARY-270'));
    }

    public function test_operation_override_requires_confirmation_and_is_consumed_when_posting(): void
    {
        $u = $this->admin();
        $account = $this->account('7009101', 'income');
        $r = Request::create('/', 'POST', ['accounting_overrides' => ['revenue' => $account->code]]);
        $r->setUserResolver(fn () => $u);
        try {
            AccountingOperationInput::validate($r, 'sale');
            $this->fail('Explicit accounting choices require confirmation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('accounting_confirmation', $e->errors());
        }
        $r->merge(['accounting_confirmation' => true]);
        $data = AccountingOperationInput::validate($r, 'sale');
        $doc = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-OVERRIDE', 'status' => 'issued', 'issue_date' => '2026-09-21', 'subtotal' => 100, 'amount' => 100, 'tax_amount' => 0, 'paid_amount' => 0, 'customer_snapshot' => [], 'accounting_overrides' => $data['accounting_overrides']]);
        app(AccountingSynchronizer::class)->syncSalesDocument($doc, $u->id);
        $this->assertTrue($doc->accountingEntry->lines()->whereHas('account', fn ($q) => $q->where('code', $account->code))->exists());
    }

    public function test_canonical_advance_nature_does_not_rewrite_historical_account_metadata(): void
    {
        $this->admin();
        foreach (['438' => 'liability', '407' => 'asset'] as $code => $nature) {
            $code = (string) $code;
            $a = LedgerAccount::where('code', $code)->firstOrFail();
            $before = $a->getAttributes();
            $this->assertSame($nature, AccountingAccountPolicy::nature($a));
            $this->assertSame($code, app(AccountingAccountPolicy::class)->valid($code, $code === '438' ? 'customer_advance' : 'supplier_advance')->code);
            $this->assertSame($before, $a->fresh()->getAttributes());
        }
    }
}
