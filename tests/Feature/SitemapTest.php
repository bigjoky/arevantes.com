<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_sitemap_contains_only_public_website_pages(): void
    {
        config()->set('app.url', 'https://www.arevantes.com');

        $response = $this->get(route('sitemap'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://www.arevantes.com/</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/aviso-legal</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/privacidad</loc>', false)
            ->assertSee('<loc>https://www.arevantes.com/cookies</loc>', false)
            ->assertDontSee('/dashboard')
            ->assertDontSee('/login');
    }
}
