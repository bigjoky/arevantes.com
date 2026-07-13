<?php

namespace Tests\Feature;

use App\Models\ActionReminder;
use App\Models\CrmActivity;
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
}
