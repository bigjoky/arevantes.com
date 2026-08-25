<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_service_has_its_own_page_and_server_rendered_metadata(): void
    {
        config(['app.url' => 'https://arevantes.com']);

        foreach (config('public_services') as $slug => $service) {
            $this->get(route('services.show', $slug))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('services/show')
                    ->where('service.slug', $slug)
                    ->where('service.form_value', $service['form_value'])
                    ->has('service.faqs', 4)
                    ->has('projects'))
                ->assertSee('<title data-inertia="title">'.$service['seo_title'].'</title>', false)
                ->assertSee('rel="canonical" href="https://arevantes.com/servicios/'.$slug.'"', false)
                ->assertSee('content="'.$service['seo_description'].'"', false)
                ->assertSee('content="https://arevantes.com'.$service['image'].'"', false)
                ->assertSee('"@type":"Service"', false)
                ->assertSee('"@type":"FAQPage"', false)
                ->assertSee($service['faqs'][0]['question'])
                ->assertDontSee('og-multirramo-v2.png', false);
        }
    }

    public function test_service_pages_only_receive_published_projects_from_their_line(): void
    {
        $this->get(route('services.show', 'energia'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 3)
                ->where('projects.0.service_slug', 'energia'));

        $this->get(route('services.show', 'seguridad'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('projects', 0));
    }

    public function test_an_unknown_service_returns_not_found(): void
    {
        $this->get('/servicios/desconocido')->assertNotFound();
    }
}
