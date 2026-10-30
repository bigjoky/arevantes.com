<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalEntry;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_mobile_queries_preserve_the_ledger_and_real_bank_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = BankAccount::create(['name' => 'Cuenta sintética M05', 'currency' => 'EUR', 'opening_balance' => '100', 'is_active' => true]);
        $account->transactions()->create(['amount' => '-10', 'currency' => 'EUR', 'booked_on' => today(), 'value_on' => today(), 'import_hash' => str_repeat('a', 64), 'description' => 'Movimiento sintético', 'status' => 'pending']);
        $this->actingAs($admin);
        $counts = fn () => [JournalEntry::count(), SalesDocumentPayment::count(), SupplierPayment::count(), BankTransaction::count()];
        $before = $counts();
        foreach (['/finance', '/finance?area=treasury', '/finance?area=forecasts', '/banking?account='.$account->id, '/banking?account='.$account->id.'&tab=reconciliation', '/billing', '/billing/payments?tab=due', '/billing/payments?tab=pending', '/billing/suppliers', '/sales'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/finance?area=treasury&account='.$account->id)->assertInertia(fn (Assert $page) => $page->component('finance/workspace'));
        $this->assertSame($before, $counts());
        $this->assertSame(100.0, (float) $account->fresh()->opening_balance);
    }

    public function test_mobile_finance_routes_do_not_grant_financial_access_to_technicians(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'technician']));
        foreach (['/finance', '/banking', '/billing', '/billing/payments', '/billing/suppliers', '/sales'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/finance/forecast-items', [])->assertForbidden();
        $this->post('/banking/accounts', [])->assertForbidden();
    }

    public function test_safe_return_context_does_not_change_invoice_financial_projection(): void
    {
        $invoice = SalesDocument::create(['number' => 'QA-M05-INV', 'type' => 'invoice', 'status' => 'issued', 'issue_date' => today(), 'due_date' => today()->addDays(3), 'subtotal' => '100', 'tax_amount' => '21', 'amount' => '121', 'paid_amount' => '0', 'customer_snapshot' => ['company_name' => 'Cliente sintético'], 'locked_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/sales/'.$invoice->id.'?return_to='.urlencode('/billing/payments?tab=due&page=2'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('billing/invoice')->where('invoice.amount', '121.00')->where('invoice.balance', '121.00'));
        $this->assertSame(0, SalesDocumentPayment::count());
    }
}
