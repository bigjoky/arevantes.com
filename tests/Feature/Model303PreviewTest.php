<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\VatReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Model303PreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_read_only_model_without_creating_returns_or_entries(): void
    {
        $this->mock(VatReport::class)->shouldReceive('make')->once()->with(2026, 3)->andReturn([
            'period' => ['year' => 2026, 'quarter' => 3], 'issued' => [], 'received' => [],
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/tax/models/303?year=2026&quarter=3')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('tax/model303')
            ->where('preview.ready', false)->where('preview.supported', true)
            ->has('preview.groups', 4)->where('preview.groups.0.base', null));
        $this->assertSame(0, TaxReturn::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_rejects_invalid_periods_and_unauthorised_users(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/tax/models/303?quarter=5')->assertSessionHasErrors('quarter');
        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get('/tax/models/303')->assertForbidden();
    }
}
