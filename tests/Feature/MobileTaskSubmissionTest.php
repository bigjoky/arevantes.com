<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileTaskSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_failure_keeps_task_input_and_does_not_create_an_activity(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->from('/operations?mobile=create')
            ->post(route('activities.store'), [
                'type' => 'task',
                'title' => '',
                'due_at' => 'not-a-date',
                'priority' => 'high',
                'assigned_user_id' => $user->id,
            ])
            ->assertRedirect('/operations?mobile=create')
            ->assertSessionHasErrors(['title', 'due_at'])
            ->assertSessionHasInput('priority', 'high')
            ->assertSessionHasInput('due_at', 'not-a-date');

        $this->assertDatabaseCount('crm_activities', 0);
    }

    public function test_existing_endpoint_accepts_the_mobile_task_contract(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->post(route('activities.store'), [
            'type' => 'task',
            'title' => 'Synthetic mobile task',
            'due_at' => '2026-10-24T12:30',
            'priority' => 'high',
            'assigned_user_id' => $user->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('crm_activities', 1);
        $activity = CrmActivity::firstOrFail();
        $this->assertSame('Synthetic mobile task', $activity->title);
        $this->assertSame($user->id, $activity->assigned_user_id);
        $this->assertSame('high', $activity->priority);
    }
}
