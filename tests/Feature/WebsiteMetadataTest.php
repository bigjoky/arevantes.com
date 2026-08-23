<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_metadata_is_present_before_javascript_runs(): void
    {
        config(['app.url' => 'https://arevantes.com']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('<title data-inertia="title">Arevantes · Energía bien instalada</title>', false)
            ->assertSee('data-inertia="description"', false)
            ->assertSee('data-inertia="og-image"', false)
            ->assertSee('content="https://arevantes.com/og.png"', false)
            ->assertSee('data-inertia="structured-data"', false)
            ->assertSee('rel="canonical" href="https://arevantes.com"', false)
            ->assertDontSee('https://www.arevantes.com', false);
    }

    public function test_public_legal_pages_have_spanish_language_and_canonical_metadata(): void
    {
        config(['app.url' => 'https://arevantes.com']);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('<title data-inertia="title">Política de privacidad · Arevantes</title>', false)
            ->assertSee('rel="canonical" href="https://arevantes.com/privacidad"', false);
    }

    public function test_public_pages_receive_baseline_security_headers(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
