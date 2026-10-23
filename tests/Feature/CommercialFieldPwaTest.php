<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialFieldPwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_launch_returns_commercial_to_field_after_login(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $url = '/commercial-field?source=pwa';
        $this->get($url)->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertRedirect(url($url));
        $this->get($url)->assertOk()->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)')->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('logout'))->assertRedirect();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_install_does_not_grant_commercial_supervision_or_unauthorized_access(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($commercial)->get('/commercial-field?source=pwa')->assertInertia(fn ($page) => $page->component('commercial-field/index')->where('supervisor', false)->where('canRecord', true));
        $technician = User::factory()->create(['role' => 'technician']);
        $this->actingAs($technician)->get('/commercial-field?source=pwa')->assertForbidden();
    }

    public function test_login_exposes_install_metadata_without_allowing_geolocation(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('/commercial-field.webmanifest', false)->assertSee('apple-mobile-web-app-capable', false)->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
