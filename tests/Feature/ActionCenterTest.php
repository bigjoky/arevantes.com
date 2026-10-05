<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CollectionAction;
use App\Models\CrmActivity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialBudget;
use App\Models\Installation;
use App\Models\PaymentBatch;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These contract assertions deliberately exercise the optional English UI.
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');
    }

    public function test_commercial_user_sees_operational_financial_and_banking_actions(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'company_name' => 'Solar Customer', 'first_name' => 'Ana']);
        $installation = Installation::create(['person_id' => $customer->id, 'project_reference' => 'SOL-24', 'site_address_line_1' => 'Calle Luz', 'site_city' => 'Cordoba', 'site_country' => 'Spain', 'service_type' => 'solar_pv', 'status' => 'active']);
        CrmActivity::create(['installation_id' => $installation->id, 'assigned_user_id' => $user->id, 'title' => 'Call customer', 'status' => 'pending', 'due_at' => now()->subDay()]);
        $sale = SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-24', 'status' => 'issued', 'issue_date' => now(), 'due_date' => now()->subDay(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);
        CollectionAction::create(['sales_document_id' => $sale->id, 'assigned_to' => $user->id, 'status' => 'promised', 'next_action_on' => today()]);
        $category = ExpenseCategory::create(['code' => 'test', 'name' => 'Test']);
        $expense = Expense::create(['expense_category_id' => $category->id, 'number' => 'EXP-24', 'type' => 'invoice', 'merchant_name' => 'Utility', 'expense_date' => now(), 'due_date' => now()->subDay(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'status' => 'pending']);
        $account = BankAccount::create(['name' => 'Main account', 'iban' => 'ES9121000418450200051332', 'currency' => 'EUR']);
        BankTransaction::create(['bank_account_id' => $account->id, 'booked_on' => now(), 'amount' => 50, 'currency' => 'EUR', 'description' => 'Pending transfer', 'import_hash' => hash('sha256', 'pending-action'), 'status' => 'pending']);
        $batch = PaymentBatch::create(['number' => 'PAY-ACTION-1', 'planned_on' => today(), 'status' => 'draft', 'created_by' => $user->id]);
        $batch->items()->create(['payable_type' => 'expense', 'payable_id' => $expense->id, 'amount' => 50]);

        $response = $this->actingAs($user)->getJson(route('action-center'));

        $response->assertOk()->assertJsonPath('total', 5);
        $this->assertEqualsCanonicalizing(
            ['Operations', 'Collections', 'Payment batches', 'Bank reconciliation', 'Financial alerts'],
            collect($response->json('groups'))->pluck('label')->all(),
        );
        $response->assertJsonFragment(['title' => 'PAY-ACTION-1 · Approval pending']);
        $response->assertJsonFragment(['title' => 'Receivables concentration']);
    }

    public function test_technician_only_sees_their_operational_actions(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        CrmActivity::create(['assigned_user_id' => $technician->id, 'title' => 'Own task', 'status' => 'pending', 'due_at' => now()]);
        CrmActivity::create(['assigned_user_id' => $other->id, 'title' => 'Private task', 'status' => 'pending', 'due_at' => now()]);

        $response = $this->actingAs($technician)->getJson(route('action-center'));

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('groups.0.label', 'Operations')
            ->assertJsonPath('groups.0.items.0.title', 'Own task');
        $this->assertNotContains('Collections', collect($response->json('groups'))->pluck('label'));
    }

    public function test_financial_alerts_are_available_as_action_center_items(): void
    {
        $this->travelTo('2026-07-26');
        $user = User::factory()->create(['role' => 'commercial']);
        FinancialBudget::create([
            'year' => 2026,
            'month' => 6,
            'category' => 'revenue',
            'amount' => 1000,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson(route('action-center'));

        $group = collect($response->json('groups'))->firstWhere('label', 'Financial alerts');
        $this->assertNotNull($group);
        $this->assertSame(1, $group['count']);
        $this->assertSame(now()->month(6)->translatedFormat('M').' · Operating result', $group['items'][0]['title']);
        $this->assertSame('critical', $group['items'][0]['severity']);
        $this->assertSame('/finance?year=2026#budget-month-6', $group['items'][0]['href']);
    }
}
