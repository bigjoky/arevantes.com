<?php

namespace Tests\Feature;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortfolioProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_and_portfolio_expose_only_published_projects(): void
    {
        PortfolioProject::create([
            'title' => 'Borrador privado',
            'slug' => 'borrador-privado',
            'service_slug' => 'seguridad',
            'summary' => 'Este proyecto no debe aparecer en la web.',
            'is_featured' => true,
            'sort_order' => 0,
        ]);

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('projects', 3)
            ->where('projects.0.slug', 'autoconsumo-vivienda-rural-axarquia'));

        $this->get(route('projects.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 3)
            ->where('projects.0.image.alt', 'Paneles fotovoltaicos integrados sobre la cubierta de una vivienda rural'));

        $this->get(route('projects.show', 'borrador-privado'))->assertNotFound();
    }

    public function test_project_detail_has_record_specific_metadata_and_images(): void
    {
        config(['app.url' => 'https://arevantes.com']);

        $this->get(route('projects.show', 'instalacion-fotovoltaica-vivienda-baena'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/show')
                ->where('project.title', 'Instalación fotovoltaica en vivienda')
                ->has('project.images', 2))
            ->assertSee('<title data-inertia="title">Instalación fotovoltaica en vivienda · Proyectos Arevantes</title>', false)
            ->assertSee('rel="canonical" href="https://arevantes.com/proyectos/instalacion-fotovoltaica-vivienda-baena"', false)
            ->assertSee('content="https://arevantes.com/proyectos/imagenes/', false)
            ->assertDontSee('og-multirramo-v2.png', false);
    }

    public function test_an_administrator_can_create_and_publish_an_optimized_project(): void
    {
        Storage::fake('local');
        config(['filesystems.private' => 'local']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('website.projects.store'), [
            'title' => 'Nuevo proyecto eléctrico',
            'service_slug' => 'electricidad',
            'location' => 'Córdoba',
            'summary' => 'Renovación integral de una instalación eléctrica residencial.',
            'description' => 'Proyecto preparado para crecer y documentado desde la primera visita.',
            'completed_on' => '2026-08-20',
            'is_featured' => '1',
            'sort_order' => '40',
            'published' => '1',
            'images' => [UploadedFile::fake()->image('cuadro.jpg', 2400, 1600)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project = PortfolioProject::where('title', 'Nuevo proyecto eléctrico')->firstOrFail();
        $image = $project->images()->firstOrFail();
        $this->assertNotNull($project->published_at);
        $this->assertSame('image/webp', $image->mime_type);
        $this->assertLessThanOrEqual(2200, max($image->width, $image->height));
        Storage::disk('local')->assertExists($image->path);

        $response = $this->get(route('project-images.show', $image))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    public function test_only_administrators_can_manage_public_projects(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)
            ->get(route('website.projects.index'))
            ->assertForbidden();
    }

    public function test_a_published_project_cannot_lose_its_last_image(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = PortfolioProject::where('slug', 'autoconsumo-vivienda-rural-axarquia')->firstOrFail();
        $image = $project->images()->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('website.project-images.destroy', $image))
            ->assertRedirect()
            ->assertSessionHasErrors('images');

        $this->assertDatabaseHas('portfolio_project_images', ['id' => $image->id]);
    }

    public function test_unpublished_images_are_not_publicly_accessible(): void
    {
        $project = PortfolioProject::create([
            'title' => 'Borrador',
            'slug' => 'borrador',
            'service_slug' => 'energia',
            'summary' => 'Contenido todavía no publicado.',
        ]);
        $image = $project->images()->create([
            'disk' => 'public_asset',
            'path' => 'images/projects/autoconsumo-vivienda-rural.webp',
            'mime_type' => 'image/webp',
            'size' => 119030,
            'width' => 1600,
            'height' => 900,
            'alt_text' => 'Imagen privada',
            'position' => 10,
        ]);

        $this->get(route('project-images.show', $image))->assertNotFound();
    }
}
