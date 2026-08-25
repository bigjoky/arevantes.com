<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_only_public_website_pages(): void
    {
        config()->set('app.url', 'https://www.arevantes.com');

        $response = $this->get(route('sitemap'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://www.arevantes.com/</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/servicios/energia</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/servicios/electricidad</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/servicios/seguridad</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/proyectos</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/proyectos/autoconsumo-vivienda-rural-axarquia</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/aviso-legal</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/privacidad</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/cookies</loc>', false)
            ->assertDontSee('/dashboard')
            ->assertDontSee('/login');
    }
}
