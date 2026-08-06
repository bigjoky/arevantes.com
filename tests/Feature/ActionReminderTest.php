<?php

namespace Tests\Feature;

use App\Models\ActionReminder;
use App\Models\CollectionAction;
use App\Models\CrmActivity;
use App\Models\FinancialBudget;
use App\Models\PaymentBatch;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\User;
use App\Notifications\ActionSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActionReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_are_sent_by_role_and_are_not_duplicated(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        CrmActivity::create(['assigned_user_id' => $technician->id, 'title' => 'Visit site', 'status' => 'pending', 'due_at' => today()]);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-REM-1', 'status' => 'issued', 'issue_date' => today(), 'due_date' => today(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);

        $this->artisan('crm:send-reminders')->expectsOutput('3 action reminders sent.')->assertSuccessful();

        Notification::assertSentTo($technician, ActionSummaryNotification::class, fn ($notification) => $notification->toArray($technician)['category'] === 'operations');
        Notification::assertSentTo($admin, ActionSummaryNotification::class, fn ($notification) => $notification->toArray($admin)['category'] === 'collections');
        Notification::assertSentTo($commercial, ActionSummaryNotification::class, fn ($notification) => $notification->toArray($commercial)['category'] === 'collections');
        Notification::assertSentToTimes($technician, ActionSummaryNotification::class, 1);
        $this->assertDatabaseCount(ActionReminder::class, 3);

        $this->artisan('crm:send-reminders')->expectsOutput('0 action reminders sent.')->assertSuccessful();
        Notification::assertCount(3);
    }

    public function test_unassigned_operational_actions_only_notify_management_roles(): void
    {
        Notification::fake();
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        CrmActivity::create(['title' => 'Assign technician', 'status' => 'pending', 'due_at' => today()]);

        $this->artisan('crm:send-reminders')->assertSuccessful();

        Notification::assertSentTo($commercial, ActionSummaryNotification::class);
        Notification::assertNotSentTo($technician, ActionSummaryNotification::class);
    }

    public function test_financial_reminders_follow_collection_actions_and_payment_batches(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $sale = SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-REM-2', 'status' => 'issued', 'issue_date' => today(), 'due_date' => today()->subMonth(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);
        CollectionAction::create(['sales_document_id' => $sale->id, 'status' => 'contacted', 'next_action_on' => today()->addMonth()]);
        PaymentBatch::create(['number' => 'PAY-REM-1', 'planned_on' => today(), 'status' => 'exported', 'created_by' => $admin->id, 'exported_at' => now()]);

        $this->artisan('crm:send-reminders')->expectsOutput('1 action reminders sent.')->assertSuccessful();

        Notification::assertSentTo($admin, ActionSummaryNotification::class, fn ($notification) => $notification->toArray($admin)['category'] === 'payments');
        Notification::assertNotSentTo($admin, ActionSummaryNotification::class, fn ($notification) => $notification->toArray($admin)['category'] === 'collections');
    }

    public function test_financial_alert_reminders_are_sent_once_per_day(): void
    {
        $this->travelTo('2026-07-26');
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        FinancialBudget::create([
            'year' => 2026,
            'month' => 6,
            'category' => 'cash_in',
            'amount' => 500,
            'created_by' => $admin->id,
        ]);

        $this->artisan('crm:send-reminders')->expectsOutput('1 action reminders sent.')->assertSuccessful();

        Notification::assertSentTo($admin, ActionSummaryNotification::class, function ($notification) use ($admin) {
            $data = $notification->toArray($admin);

            return $data['category'] === 'financial_alerts'
                && $data['count'] === 1
                && str_ends_with($data['url'], '/finance?year=2026#financial-alerts');
        });
        $this->assertDatabaseHas('action_reminders', [
            'user_id' => $admin->id,
            'category' => 'financial_alerts',
            'item_count' => 1,
        ]);

        $this->artisan('crm:send-reminders')->expectsOutput('0 action reminders sent.')->assertSuccessful();
        Notification::assertSentToTimes($admin, ActionSummaryNotification::class, 1);
    }
}
