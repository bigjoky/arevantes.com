<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicServicesTest extends TestCase
{
    public function test_each_service_has_its_own_page_and_server_rendered_metadata(): void
    {
        config(['app.url' => 'https://arevantes.com']);

        foreach (config('public_services') as $slug => $service) {
            $this->get(route('services.show', $slug))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('services/show')
                    ->where('service.slug', $slug)
                    ->where('service.form_value', $service['form_value']))
                ->assertSee('<title data-inertia="title">'.$service['seo_title'].'</title>', false)
                ->assertSee('rel="canonical" href="https://arevantes.com/servicios/'.$slug.'"', false)
                ->assertSee('content="'.$service['seo_description'].'"', false)
                ->assertSee('content="https://arevantes.com'.$service['image'].'"', false)
                ->assertSee('"@type":"Service"', false)
                ->assertDontSee('og-multirramo-v2.png', false);
        }
    }

    public function test_an_unknown_service_returns_not_found(): void
    {
        $this->get('/servicios/desconocido')->assertNotFound();
    }
}
