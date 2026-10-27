<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use App\Models\SalesDocument;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class BillingRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(array $attributes = []): SalesDocument
    {
        return SalesDocument::create([...['number' => 'BILL-'.Str::random(12), 'type' => 'invoice', 'status' => 'issued', 'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(5)->toDateString(), 'subtotal' => '100.00', 'tax_amount' => '21.00', 'amount' => '121.00', 'paid_amount' => '0.00', 'customer_snapshot' => ['company_name' => 'Cliente histórico'], 'locked_at' => now()], ...$attributes]);
    }

    public function test_dashboard_uses_all_real_invoices_credits_and_effective_payments(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $invoice = $this->invoice(['paid_amount' => '20.00', 'due_date' => today()->subDay()->toDateString()]);
        $invoice->payments()->create(['paid_at' => today()->toDateString(), 'amount' => '20.00', 'method' => 'bank_transfer']);
        $this->invoice(['type' => 'credit_note', 'source_document_id' => $invoice->id, 'amount' => '-11.00', 'subtotal' => '-10.00', 'tax_amount' => '-1.00']);
        $this->invoice(['status' => 'draft', 'amount' => '9999.00', 'locked_at' => null]);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Proveedor real', 'country' => 'ES']);
        $bill = EconomicFixture::purchase(['type' => 'supplier_invoice', 'number' => 'SUP-1', 'supplier_id' => $supplier->id, 'status' => 'pending', 'issue_date' => today()->toDateString(), 'due_date' => today()->toDateString(), 'subtotal' => '50.00', 'tax_amount' => '10.50', 'amount' => '60.50', 'paid_amount' => '10.00']);
        SupplierPayment::create(['purchase_document_id' => $bill->id, 'paid_at' => today()->toDateString(), 'amount' => '10.00', 'method' => 'bank_transfer']);
        EconomicFixture::purchase(['type' => 'purchase_order', 'number' => 'PO-1', 'supplier_id' => $supplier->id, 'status' => 'draft', 'issue_date' => today()->toDateString(), 'subtotal' => '10000', 'tax_amount' => '2100', 'amount' => '12100', 'paid_amount' => '0']);
        $this->actingAs($user)->get('/billing')->assertOk()->assertInertia(fn (Assert $page) => $page->component('billing/dashboard')->where('metrics.invoiced', '110.00')->where('metrics.receivable', '90.00')->where('metrics.payable', '50.50')->where('metrics.overdue', '90.00')->where('metrics.received', '20.00')->where('metrics.paid', '10.00')->where('needs', null));
    }

    public function test_lists_paginate_sort_filter_and_keep_document_and_payment_states_separate(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        for ($i = 0; $i < 12; $i++) {
            $this->invoice(['number' => 'BILL-'.sprintf('%02d', $i)]);
        }
        $this->invoice(['number' => 'PARTIAL', 'paid_amount' => '1.00']);
        $this->actingAs($user)->get('/sales')->assertInertia(fn (Assert $page) => $page->component('billing/invoices')->has('invoices.data', 10)->where('invoices.total', 13));
        $this->get('/sales?state=partial')->assertInertia(fn (Assert $page) => $page->where('invoices.total', 1)->where('invoices.data.0.number', 'PARTIAL')->where('invoices.data.0.document_state', 'issued')->where('invoices.data.0.payment_state', 'partial'));
        $this->get('/sales?search=absent')->assertInertia(fn (Assert $page) => $page->where('invoices.total', 0));
        $this->get('/sales?sort=number&direction=asc')->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.number', 'BILL-00'));
    }

    public function test_supplier_projection_includes_independent_expense_invoices_and_excludes_receipts(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        foreach (['invoice', 'receipt'] as $type) {
            EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => $type, 'number' => 'EXP-'.$type, 'document_number' => 'Original-'.$type, 'issuer_legal_name' => 'Proveedor independiente', 'expense_date' => today()->toDateString(), 'currency_code' => 'EUR', 'subtotal' => '50', 'tax_amount' => '10.50', 'amount' => '60.50', 'paid_amount' => '0', 'status' => 'pending']);
        }
        $this->actingAs($user)->get('/billing/suppliers')->assertInertia(fn (Assert $page) => $page->component('billing/invoices')->where('invoices.total', 1)->where('invoices.data.0.kind', 'expense'));
        $expense = Expense::where('type', 'invoice')->firstOrFail();
        $this->get('/billing/suppliers/expense/'.$expense->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('billing/invoice')->where('detail.document_number', 'Original-invoice'));
        $this->get('/billing/payments?tab=pending')->assertOk()->assertInertia(fn (Assert $page) => $page->component('billing/payments')->where('pending.total', 1));
    }

    public function test_due_buckets_do_not_overlap_and_today_is_not_overdue(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        foreach ([0, 1, 2, 7, 8, 30, 31] as $day) {
            $this->invoice(['due_date' => today()->addDays($day)->toDateString()]);
        }
        $this->actingAs($user)->get('/billing')->assertInertia(fn (Assert $page) => $page->where('counts.overdue', 0)->where('upcoming.0.count', 1)->where('upcoming.1.count', 1)->where('upcoming.2.count', 2)->where('upcoming.3.count', 2));
        $this->get('/sales?state=pending&due_from='.today()->addDays(2)->toDateString().'&due_to='.today()->addDays(7)->toDateString())->assertInertia(fn (Assert $page) => $page->where('invoices.total', 2));
    }

    public function test_financial_data_is_not_exposed_to_commercial_users(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        Gate::define('view-finance', fn () => false);
        $this->actingAs($user)->get('/billing')->assertOk()->assertInertia(fn (Assert $page) => $page->where('metrics.payable', null)->where('metrics.received', null)->where('suppliers', null)->where('movements', null));
        $this->get('/billing/suppliers')->assertForbidden();
        $this->get('/billing/payments')->assertForbidden();
    }

    public function test_invoice_detail_is_full_width_and_duplicate_does_not_copy_final_number_or_money_movements(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $invoice = $this->invoice(['paid_amount' => '121.00', 'status' => 'paid']);
        $this->actingAs($user)->get('/sales/'.$invoice->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('billing/invoice')->where('invoice.document_state', 'issued')->where('capabilities.edit', false));
        $this->post('/sales/'.$invoice->id.'/duplicate')->assertRedirect();
        $copy = SalesDocument::latest('id')->firstOrFail();
        $this->assertNotEquals($invoice->number, $copy->number);
        $this->assertEquals('draft', $copy->status);
        $this->assertNull($copy->locked_at);
        $this->assertNull($copy->document_series_id);
        $this->assertEquals('0.00', $copy->paid_amount);
        $this->assertEquals(0, $copy->payments()->count());
        $this->assertEquals($invoice->customer_snapshot, $copy->customer_snapshot);
    }

    public function test_customer_payment_retries_are_idempotent_and_balance_includes_issued_credits(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $invoice = $this->invoice();
        $this->invoice(['type' => 'credit_note', 'source_document_id' => $invoice->id, 'amount' => '-21.00', 'subtotal' => '-20.00', 'tax_amount' => '-1.00']);
        $data = ['idempotency_key' => (string) Str::uuid(), 'paid_at' => today()->toDateString(), 'amount' => '100.00', 'method' => 'bank_transfer'];
        $this->actingAs($user)->post('/sales/'.$invoice->id.'/payments', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/sales/'.$invoice->id.'/payments', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame('100.00', $invoice->fresh()->paid_amount);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->post('/sales/'.$invoice->id.'/payments', [...$data, 'amount' => '99.00'])->assertSessionHasErrors('amount');
    }

    public function test_customer_payments_reject_excess_precision_overpayment_and_unauthorized_writes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $invoice = $this->invoice();
        $data = ['paid_at' => today()->toDateString(), 'amount' => '121.01', 'method' => 'bank_transfer'];
        $this->actingAs($user)->post('/sales/'.$invoice->id.'/payments', $data)->assertSessionHasErrors('amount');
        $this->post('/sales/'.$invoice->id.'/payments', [...$data, 'amount' => '1.001'])->assertSessionHasErrors('amount');
        Gate::define('manage-treasury', fn () => false);
        $this->post('/sales/'.$invoice->id.'/payments', [...$data, 'amount' => '1.00'])->assertForbidden();
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_issued_pdf_is_archived_and_stays_identical_after_company_changes(): void
    {
        Storage::fake(config('filesystems.private'));
        $user = User::factory()->create(['role' => 'admin']);
        $invoice = $this->invoice(['status' => 'draft', 'locked_at' => null]);
        $this->actingAs($user)->post('/sales/'.$invoice->id.'/issue', ['issue_date' => today()->toDateString()])->assertRedirect()->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertNotNull($invoice->archived_pdf_path);
        $first = $this->get('/sales/'.$invoice->id.'/pdf')->assertOk()->getContent();
        CompanyProfile::firstOrFail()->update(['legal_name' => 'Identidad modificada después de emisión']);
        $second = $this->get('/sales/'.$invoice->id.'/pdf')->assertOk()->getContent();
        $this->assertSame($first, $second);
        $this->assertSame(hash('sha256', $first), $invoice->archived_pdf_hash);
        $this->post('/sales/'.$invoice->id.'/issue', ['issue_date' => today()->toDateString()])->assertUnprocessable();
        $this->assertSame($invoice->number, $invoice->fresh()->number);
    }

    public function test_sidebar_has_a_navigable_billing_parent_and_exactly_three_children(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $billing = collect(app(WorkspaceNavigation::class)->for($user))->firstWhere('id', 'billing');
        $this->assertSame('/billing', $billing['href']);
        $this->assertTrue($billing['navigable']);
        $this->assertSame(['Facturas de clientes', 'Facturas de proveedores', 'Cobros y pagos'], array_column($billing['children'], 'title'));
    }

    public function test_supplier_payment_retries_do_not_duplicate_and_reject_changed_payloads(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Proveedor de prueba', 'country' => 'ES']);
        $invoice = EconomicFixture::purchase(['type' => 'supplier_invoice', 'number' => 'SUP-PAY', 'supplier_id' => $supplier->id, 'status' => 'pending', 'matching_status' => 'matched', 'issue_date' => today()->toDateString(), 'subtotal' => '100', 'tax_amount' => '21', 'amount' => '121', 'paid_amount' => '0']);
        $data = ['idempotency_key' => (string) Str::uuid(), 'paid_at' => today()->toDateString(), 'amount' => '21.00', 'method' => 'cash', 'funding_source_type' => 'ledger_account', 'funding_source_id' => LedgerAccount::where('code', '570')->firstOrFail()->id];
        $this->actingAs($user)->post('/supplier-invoices/'.$invoice->id.'/payments', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/supplier-invoices/'.$invoice->id.'/payments', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame('21.00', $invoice->fresh()->paid_amount);
        $this->post('/supplier-invoices/'.$invoice->id.'/payments', [...$data, 'amount' => '22.00'])->assertSessionHasErrors('amount');
        $this->post('/supplier-invoices/'.$invoice->id.'/payments', [...$data, 'idempotency_key' => (string) Str::uuid(), 'amount' => '100.01'])->assertSessionHasErrors('amount');
    }

    public function test_rectification_retries_reuse_the_document_and_overcredit_is_rejected(): void
    {
        Storage::fake(config('filesystems.private'));
        $invoice = $this->invoice();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/sales/'.$invoice->id.'/credit-note')->assertRedirect();
        $this->post('/sales/'.$invoice->id.'/credit-note')->assertRedirect();
        $this->assertSame(1, $invoice->childDocuments()->where('type', 'credit_note')->count());
        $credit = $invoice->childDocuments()->firstOrFail();
        $this->assertSame('-121.00', $credit->amount);
        $this->invoice(['type' => 'credit_note', 'source_document_id' => $invoice->id, 'amount' => '-1.00']);
        $this->post('/sales/'.$credit->id.'/issue', ['issue_date' => today()->toDateString()])->assertSessionHasErrors();
        $this->assertSame('draft', $credit->fresh()->status);
    }

    public function test_treasury_pending_and_due_views_use_invoice_balances(): void
    {
        $this->invoice();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/billing/payments?tab=pending')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.tab', 'pending')->where('pending.total', 1)->where('pending.data.0.balance', '121.00'));
        $this->get('/billing/payments?tab=due')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.tab', 'due')->where('pending.total', 1));
    }
}
