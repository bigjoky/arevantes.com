<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use App\Notifications\WebsiteLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_paginated_and_confined_to_the_current_user(): void
    {
        $owner = User::factory()->create(['role' => 'commercial']);
        $other = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Prueba']);
        $lead = Opportunity::create(['person_id' => $customer->id, 'owner_user_id' => $owner->id, 'title' => 'Consulta', 'stage' => 'new', 'probability' => 10, 'estimated_value' => 0]);
        for ($i = 0; $i < 26; $i++) {
            $owner->notify(new WebsiteLeadNotification($lead));
        }
        $other->notify(new WebsiteLeadNotification($lead));
        $otherNotification = $other->notifications()->firstOrFail();
        $ownNotification = $owner->notifications()->firstOrFail();

        $this->actingAs($owner)->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')->has('notifications.data', 25)->where('notifications.total', 26));
        $this->actingAs($owner)->post('/notifications/'.$otherNotification->id.'/read')->assertNotFound();
        $this->actingAs($owner)->post('/notifications/'.$ownNotification->id.'/read')->assertRedirect();
        $this->assertNotNull($ownNotification->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
        auth()->logout();
        $this->get('/notifications')->assertRedirect('/login');
    }
}
