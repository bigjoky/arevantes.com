<?php

namespace Tests\Feature;

use App\Models\AccountingEntryCorrection;
use App\Models\AccountingPeriod;
use App\Models\AccountingReclassificationBatch;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingReclassification;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\CustomerPaymentCancellation;
use App\Services\Accounting\JournalPoster;
use App\Services\Accounting\PartyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class AccountingConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->actor);
    }

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(['code' => $code], ['name' => 'Synthetic account', 'type' => 'asset', 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
    }

    private function invoice(Person $person, string $code, int $amount = 100, string $date = '2026-09-21'): SalesDocument
    {
        $this->account($code);
        $person->update(['receivable_account_code' => $code]);
        $d = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-'.Str::uuid(), 'person_id' => $person->id, 'status' => 'issued', 'issue_date' => $date, 'amount' => $amount, 'subtotal' => $amount, 'tax_amount' => 0, 'paid_amount' => 0, 'customer_snapshot' => []]);
        app(AccountingSynchronizer::class)->syncSalesDocument($d, $this->actor->id);

        return $d;
    }

    private function request(array $data = []): Request
    {
        $r = Request::create('/', 'GET', ['ledger_year' => 2026, ...$data]);
        $r->setUserResolver(fn () => $this->actor);

        return $r;
    }

    private function selection(SalesDocument $doc, string $new): array
    {
        $this->account($new);

        return ['operation_key' => (string) Str::uuid(), 'entry_date' => '2026-09-22', 'reason' => 'Synthetic supervised reclassification', 'items' => [['line_id' => $doc->accountingEntry->lines()->whereHas('account', fn ($q) => $q->where('code', 'like', '430%'))->firstOrFail()->id, 'account_code' => $new]]];
    }

    public function test_shared_accounts_attribute_only_document_evidence_and_preserve_period_balances(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic one']);
        $other = Person::create(['category' => 'customer', 'first_name' => 'Synthetic other']);
        $first = $this->invoice($p, '4309101');
        $this->invoice($p, '430', 200, '2026-10-10');
        $this->invoice($other, '430', 700, '2026-10-10');
        $payment = $first->payments()->create(['amount' => 40, 'paid_at' => '2026-09-25', 'method' => 'bank_transfer']);
        app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $this->actor->id);
        app(JournalPoster::class)->post('2026-10-11', 'Synthetic unidentified shared account', [['account_code' => '430', 'debit' => 90, 'credit' => 0], ['account_code' => '700', 'debit' => 0, 'credit' => 90]], $this->actor->id);
        $s = app(PartyLedger::class)->summary('customer', $p->id, $this->request(['ledger_from' => '2026-10-01', 'ledger_to' => '2026-10-31']));
        $this->assertSame('60.00', $s['opening']);
        $this->assertSame('260.00', $s['closing']);
        $this->assertSame('200.00', $s['debit']);
        $this->assertSame(1, $s['unattributed_count']);
        $this->assertCount(2, $s['accounts']);
        $this->get('/accounting/parties/customer/'.$p->id.'?ledger_year=2026')->assertOk();
        $this->get('/accounting/reclassification?year=2026')->assertOk();
    }

    public function test_reclassification_is_read_only_until_confirmation_and_replays_without_duplicate_entries(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $d = $this->invoice($p, '4309201');
        $data = $this->selection($d, '4309202');
        $service = app(AccountingReclassification::class);
        $before = $d->accountingEntry->load('lines')->toArray();
        $count = JournalEntry::count();
        $plan = $service->preview($data, $this->actor);
        $this->assertSame($count, JournalEntry::count());
        $this->assertSame(1, $plan['eligible_count']);
        $this->assertCount(2, $plan['account_totals']);
        $this->assertSame(0, AccountingReclassificationBatch::count());
        $body = [...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true];
        $batch = $service->execute($body, $this->actor);
        $again = $service->execute($body, $this->actor);
        $this->assertSame($batch->id, $again->id);
        $this->assertSame($count + 1, JournalEntry::count());
        $this->assertSame($before, $d->accountingEntry->fresh()->load('lines')->toArray());
        $s = app(PartyLedger::class)->summary('customer', $p->id, $this->request());
        $this->assertSame('100.00', $s['closing']);
        $this->assertSame('0.00', $s['accounts']->firstWhere('code', '4309201')['closing']);
        $this->assertSame('100.00', $s['accounts']->firstWhere('code', '4309202')['closing']);
        $this->get('/accounting/reclassification/'.$batch->id)->assertOk();
    }

    public function test_stale_preview_closed_periods_permissions_and_treasury_are_protected(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $d = $this->invoice($p, '4309301');
        $data = $this->selection($d, '4309302');
        $svc = app(AccountingReclassification::class);
        $plan = $svc->preview($data, $this->actor);
        AccountingPeriod::updateOrCreate(['year' => 2026, 'month' => 9], ['fiscal_year_id' => $d->accountingEntry->fiscal_year_id, 'status' => 'closed']);
        try {
            $svc->execute([...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true], $this->actor);
            $this->fail('Stale eligibility must stop execution.');
        } catch (ValidationException) {
        }
        $this->assertSame(0, AccountingEntryCorrection::count());
        $this->assertSame(0, AccountingReclassificationBatch::count());
        $this->actingAs(User::factory()->create(['role' => 'technician']));
        $this->get('/accounting/parties/customer/'.$p->id)->assertForbidden();
        $this->post('/accounting/reclassification/preview', $data)->assertForbidden();
    }

    public function test_batch_mixes_are_explicitly_rejected_and_failed_batches_roll_back_all_adjustments(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $d = $this->invoice($p, '4309401');
        $e = $this->invoice($p, '4309401');
        $data = $this->selection($d, '4309402');
        $data['items'] = array_merge($data['items'], $this->selection($e, '4309402')['items']);
        $svc = app(AccountingReclassification::class);
        $plan = $svc->preview($data, $this->actor);
        $count = JournalEntry::count();
        AccountingReclassificationBatch::creating(fn () => throw new \RuntimeException('Synthetic transactional failure'));
        try {
            $svc->execute([...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true], $this->actor);
            $this->fail('Expected injected failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic transactional failure', $e->getMessage());
        }
        $this->assertSame($count, JournalEntry::count());
        $this->assertSame(0, AccountingEntryCorrection::count());
        $this->assertSame(0, AccountingReclassificationBatch::count());
    }

    public function test_partially_blocked_batch_requires_explicit_confirmation_and_leaves_treasury_unchanged(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $d = $this->invoice($p, '4309501');
        $data = $this->selection($d, '4309502');
        $entry = app(JournalPoster::class)->post('2026-09-21', 'Synthetic bank line', [['account_code' => '572', 'debit' => 10, 'credit' => 0], ['account_code' => '700', 'debit' => 0, 'credit' => 10]], $this->actor->id);
        $bank = $entry->lines()->whereHas('account', fn ($q) => $q->where('code', '572'))->firstOrFail();
        $this->account('5729501');
        $data['items'][] = ['line_id' => $bank->id, 'account_code' => '5729501'];
        $svc = app(AccountingReclassification::class);
        $plan = $svc->preview($data, $this->actor);
        $this->assertSame(1, $plan['eligible_count']);
        $this->assertCount(1, $plan['blocked']);
        $body = [...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true];
        try {
            $svc->execute($body, $this->actor);
            $this->fail('Partial selection needs explicit authorization.');
        } catch (ValidationException) {
        }
        $this->assertSame(0, AccountingEntryCorrection::count());
        $batch = $svc->execute([...$body, 'confirm_partial' => true], $this->actor);
        $this->assertCount(1, $batch->results['corrections']);
        $this->assertSame('572', $bank->fresh()->account->code);
    }

    public function test_two_different_parties_cannot_be_silently_combined(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic one']);
        $o = Person::create(['category' => 'customer', 'first_name' => 'Synthetic other']);
        $d = $this->invoice($p, '4309601');
        $e = $this->invoice($o, '4309601');
        $data = $this->selection($d, '4309602');
        $data['items'] = array_merge($data['items'], $this->selection($e, '4309602')['items']);
        $this->expectException(ValidationException::class);
        app(AccountingReclassification::class)->preview($data, $this->actor);
    }

    public function test_customer_advance_is_separate_and_drafts_are_excluded(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $d = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-ADVANCE', 'person_id' => $p->id, 'status' => 'draft', 'issue_date' => '2026-09-21', 'amount' => 100, 'subtotal' => 100, 'tax_amount' => 0, 'paid_amount' => 0, 'customer_snapshot' => [], 'collect_before_invoice' => true]);
        $payment = $d->payments()->create(['amount' => 100, 'paid_at' => '2026-09-21', 'method' => 'bank_transfer']);
        app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $this->actor->id);
        $s = app(PartyLedger::class)->summary('customer', $p->id, $this->request());
        $this->assertSame('0.00', $s['closing']);
        $this->assertSame('100.00', $s['advance']);
        $this->assertSame('-100.00', $s['consolidated_closing']);
    }

    public function test_audited_party_changes_remain_separate_when_per_line_identity_is_ambiguous(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic original']);
        $other = Person::create(['category' => 'customer', 'first_name' => 'Synthetic replacement']);
        $d = $this->invoice($p, '4309701');
        $d->update(['person_id' => $other->id]);
        AuditLog::create(['user_id' => $this->actor->id, 'event' => 'issued_invoice_corrected', 'auditable_type' => SalesDocument::class, 'auditable_id' => $d->id, 'old_values' => ['person_id' => $p->id], 'new_values' => ['person_id' => $other->id]]);
        foreach ([$p, $other] as $person) {
            $s = app(PartyLedger::class)->summary('customer', $person->id, $this->request());
            $this->assertSame('0.00', $s['closing']);
            $this->assertGreaterThan(0, $s['unattributed_count']);
            $this->get('/accounting/parties/customer/'.$person->id.'?ledger_year=2026&unattributed=1')->assertOk();
        }
    }

    public function test_supplier_control_uses_credit_nature_and_does_not_include_another_supplier(): void
    {
        $supplier = EconomicFixture::supplier(['legal_name' => 'Synthetic creditor']);
        $doc = EconomicFixture::purchase(['type' => 'supplier_invoice', 'number' => 'SYN-SUPPLIER', 'supplier_id' => $supplier->id, 'status' => 'received', 'issue_date' => '2026-09-21', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 0]);
        app(AccountingSynchronizer::class)->syncPurchaseDocument($doc, $this->actor->id);
        $s = app(PartyLedger::class)->summary('supplier', $supplier->id, $this->request());
        $this->assertSame('100.00', $s['closing']);
        $this->assertSame('100.00', $s['credit']);
    }

    public function test_receipts_use_accounts_effective_on_their_dates_and_reversal_never_predates_an_adjustment(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic chronological']);
        $d = $this->invoice($p, '4309801');
        $data = $this->selection($d, '4309802');
        $data['entry_date'] = '2026-10-01';
        $svc = app(AccountingReclassification::class);
        $plan = $svc->preview($data, $this->actor);
        $svc->execute([...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true], $this->actor);
        foreach (['2026-09-25' => '4309801', '2026-10-02' => '4309802'] as $date => $code) {
            $payment = $d->payments()->create(['amount' => 20, 'paid_at' => $date, 'method' => 'bank_transfer']);
            app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $this->actor->id);
            $this->assertTrue($payment->accountingEntry->lines()->whereHas('account', fn ($q) => $q->where('code', $code))->where('credit', '20.00')->exists());
        }
        $payment = $d->payments()->whereDate('paid_at', '2026-09-25')->firstOrFail();
        $this->account('4309803');
        $line = $payment->accountingEntry->lines()->whereHas('account', fn ($q) => $q->where('code', '4309801'))->firstOrFail();
        $data = ['operation_key' => (string) Str::uuid(), 'entry_date' => '2026-10-03', 'reason' => 'Synthetic chronological receipt reclassification', 'items' => [['line_id' => $line->id, 'account_code' => '4309803']]];
        $plan = $svc->preview($data, $this->actor);
        $batch = $svc->execute([...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true], $this->actor);
        $adjustment = JournalEntry::findOrFail($batch->results['corrections'][0]['adjustment_entry_id']);
        app(CustomerPaymentCancellation::class)->cancel($d->fresh(), $payment->id, $this->actor, 'Synthetic chronological cancellation', (string) Str::uuid(), $payment->fresh()->cancellationRevision());
        $this->assertSame('2026-10-03', $adjustment->fresh()->reversal->entry_date->toDateString());
        $this->assertSame(0, DB::query()->fromSub(DB::table('journal_lines')->select('journal_entry_id')->groupBy('journal_entry_id')->havingRaw('sum(debit)<>sum(credit)'), 'unbalanced')->count());
    }

    public function test_supplier_payment_reclassification_remains_reversible_with_originals_preserved(): void
    {
        $supplier = EconomicFixture::supplier(['legal_name' => 'Synthetic supplier reversal']);
        $expense = Expense::create(['number' => 'SYN-EXP-BATCH', 'supplier_id' => $supplier->id, 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'receipt', 'merchant_name' => 'Synthetic supplier', 'expense_date' => '2026-09-20', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 100, 'status' => 'paid']);
        app(AccountingSynchronizer::class)->syncExpense($expense, $this->actor->id);
        $payment = $expense->payments()->create(['created_by' => $this->actor->id, 'paid_at' => '2026-09-21', 'amount' => 100, 'method' => 'cash']);
        app(AccountingSynchronizer::class)->syncSupplierPayment($payment, $this->actor->id);
        $entry = $payment->accountingEntry;
        $before = $entry->load('lines')->toArray();
        $line = $entry->lines()->with('account')->whereHas('account', fn ($q) => $q->where('code', 'like', '4%'))->firstOrFail();
        $code = substr($line->account->code, 0, 3).'9998';
        LedgerAccount::create(['code' => $code, 'name' => 'Synthetic chosen supplier account', 'type' => 'liability', 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
        $data = ['operation_key' => (string) Str::uuid(), 'entry_date' => '2026-09-22', 'reason' => 'Synthetic supervised supplier reclassification', 'items' => [['line_id' => $line->id, 'account_code' => $code]]];
        $svc = app(AccountingReclassification::class);
        $plan = $svc->preview($data, $this->actor);
        $batch = $svc->execute([...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true], $this->actor);
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Synthetic supplier cancellation'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before, $entry->fresh()->load('lines')->toArray());
        $s = app(PartyLedger::class)->summary('supplier', $supplier->id, $this->request());
        $this->assertSame('100.00', $s['consolidated_closing']);
        $adjustment = JournalEntry::findOrFail($batch->results['corrections'][0]['adjustment_entry_id']);
        $this->assertSame('2026-09-22', $adjustment->fresh()->reversal->entry_date->toDateString());
    }

    public function test_tax_protection_covers_the_adjustment_period_and_its_automatic_reversal(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic tax protected']);
        $doc = $this->invoice($p, '4309901');
        $payment = $doc->payments()->create(['amount' => 40, 'paid_at' => '2026-09-21', 'method' => 'bank_transfer']);
        app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $this->actor->id);
        $this->account('4309902');
        $line = $payment->accountingEntry->lines()->whereHas('account', fn ($q) => $q->where('code', '4309901'))->firstOrFail();
        $data = ['operation_key' => (string) Str::uuid(), 'entry_date' => '2026-10-01', 'reason' => 'Synthetic protected target period', 'items' => [['line_id' => $line->id, 'account_code' => '4309902']]];
        $svc = app(AccountingReclassification::class);
        $plan = $svc->preview($data, $this->actor);
        $svc->execute([...$data, 'preview_revision' => $plan['preview_revision'], 'confirmed' => true], $this->actor);
        TaxReturn::create(['year' => 2026, 'quarter' => 4, 'prepared_at' => now(), 'output_vat' => 0, 'input_vat' => 0, 'result' => 0, 'snapshot' => [], 'period_from' => '2026-10-01', 'period_to' => '2026-12-31', 'status' => 'prepared']);
        $count = JournalEntry::count();
        try {
            app(CustomerPaymentCancellation::class)->cancel($doc->fresh(), $payment->id, $this->actor, 'Synthetic protected reversal', (string) Str::uuid(), $payment->fresh()->cancellationRevision());
            $this->fail('The adjustment period must remain protected.');
        } catch (ValidationException) {
        }
        $this->assertSame($count, JournalEntry::count());
        $this->assertNull($payment->fresh()->voided_at);
        $this->account('4309903');
        $data['operation_key'] = (string) Str::uuid();
        $data['items'][0]['account_code'] = '4309903';
        $plan = $svc->preview($data, $this->actor);
        $this->assertSame(0, $plan['eligible_count']);
        $this->assertCount(1, $plan['blocked']);
    }

    public function test_http_preview_accepts_unconfirmed_form_and_execution_requires_confirmation(): void
    {
        $p = Person::create(['category' => 'customer', 'first_name' => 'Synthetic HTTP']);
        $doc = $this->invoice($p, '4309911');
        $data = [...$this->selection($doc, '4309912'), 'confirmed' => false, 'confirm_partial' => false];
        $plan = $this->postJson('/accounting/reclassification/preview', $data)->assertOk()->json();
        $this->assertSame(0, AccountingReclassificationBatch::count());
        $this->assertSame(0, AccountingEntryCorrection::count());
        $body = [...$data, 'preview_revision' => $plan['preview_revision']];
        $this->post('/accounting/reclassification', $body)->assertSessionHasErrors('confirmed');
        $body['confirmed'] = true;
        $this->post('/accounting/reclassification', $body)->assertRedirect(route('accounting.reclassification.batch', $data['operation_key']));
        $this->post('/accounting/reclassification', $body)->assertRedirect(route('accounting.reclassification.batch', $data['operation_key']));
        $this->assertSame(1, AccountingReclassificationBatch::count());
        $this->assertSame(1, AccountingEntryCorrection::count());
    }

    public function test_treasury_targets_and_employee_funding_cannot_be_reclassified_by_the_generic_tool(): void
    {
        foreach ([['5409911', '5729911', 'asset'], ['4659911', '4659912', 'liability']] as [$old,$new,$type]) {
            foreach ([$old, $new] as $code) {
                LedgerAccount::firstOrCreate(['code' => $code], ['name' => 'Synthetic protected account', 'type' => $type, 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
            }$entry = app(JournalPoster::class)->post('2026-09-21', 'Synthetic protected funding', [['account_code' => $old, 'debit' => 10, 'credit' => 0], ['account_code' => '629', 'debit' => 0, 'credit' => 10]], $this->actor->id);
            $line = $entry->lines()->whereHas('account', fn ($q) => $q->where('code', $old))->firstOrFail();
            $plan = app(AccountingReclassification::class)->preview(['operation_key' => (string) Str::uuid(), 'entry_date' => '2026-09-22', 'reason' => 'Synthetic protected destination', 'items' => [['line_id' => $line->id, 'account_code' => $new]]], $this->actor);
            $this->assertSame(0, $plan['eligible_count']);
            $this->assertCount(1, $plan['blocked']);
        }
        $this->assertSame(0, AccountingEntryCorrection::count());
    }
}
