<?php

namespace Tests\Feature;

use App\Support\BusinessLineCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCorporatePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_corporate_pages_are_public_with_server_metadata_and_no_workspace_payload(): void
    {
        config(['app.url' => 'https://arevantes.com']);
        foreach (['/servicios', '/empresa', '/proceso', '/contacto', '/servicios/redes'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('rel="canonical" href="https://arevantes.com'.$path.'"', false)
                ->assertInertia(fn (Assert $page) => $page
                    ->component($path === '/contacto' ? 'public/contact' : 'public/page')
                    ->where('metadata.path', $path)
                    ->where('workspaceContext', null)
                    ->where('auth.navigation', [])
                    ->where('auth.abilities', []));
            $this->get('/sitemap.xml')->assertSee('<loc>https://arevantes.com'.$path.'</loc>', false);
        }
    }

    public function test_contact_only_offers_supported_backend_categories(): void
    {
        $this->get('/contacto?servicio=electrical')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contactServices', BusinessLineCatalog::WEBSITE_SERVICES)
                ->where('selectedService', 'electrical'));
        $this->get('/contacto?servicio=redes')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selectedService', ''));
    }

    public function test_descriptive_service_aliases_redirect_to_preserved_routes(): void
    {
        $this->get('/servicios/energias-renovables')->assertStatus(301)->assertRedirect('/servicios/energia');
        $this->get('/servicios/instalaciones-electricas')->assertStatus(301)->assertRedirect('/servicios/electricidad');
        $this->get('/servicios/seguridad-y-control')->assertStatus(301)->assertRedirect('/servicios/seguridad');
        $this->get('/actualidad')->assertNotFound();
    }
}
