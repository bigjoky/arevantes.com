<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Support\PublicProjectPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'servicio' => ['nullable', Rule::in(array_keys(config('public_services', [])))],
        ]);

        $projects = PortfolioProject::query()
            ->published()
            ->with('images')
            ->when($filters['servicio'] ?? null, fn ($query, string $service) => $query->where('service_slug', $service))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('completed_on')
            ->get()
            ->map(PublicProjectPresenter::summary(...));

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'activeService' => $filters['servicio'] ?? null,
            'services' => collect(config(app()->isLocale('en') ? 'public_services_en' : 'public_services', []))->map(fn (array $service): array => [
                'slug' => $service['slug'],
                'name' => $service['short_name'],
            ])->values(),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function show(string $project): Response
    {
        $project = PortfolioProject::query()
            ->published()
            ->with('images')
            ->where('slug', $project)
            ->firstOrFail();

        $related = PortfolioProject::query()
            ->published()
            ->with('images')
            ->whereKeyNot($project->id)
            ->orderByRaw('service_slug = ? desc', [$project->service_slug])
            ->orderByDesc('is_featured')
            ->orderByDesc('completed_on')
            ->limit(3)
            ->get()
            ->map(PublicProjectPresenter::summary(...));

        return Inertia::render('projects/show', [
            'project' => PublicProjectPresenter::detail($project),
            'relatedProjects' => $related,
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }
}
