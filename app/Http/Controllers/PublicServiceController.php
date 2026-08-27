<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Support\PublicProjectPresenter;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class PublicServiceController extends Controller
{
    public function __invoke(string $service): Response
    {
        $services = config(app()->isLocale('en') ? 'public_services_en' : 'public_services', []);
        abort_unless(Arr::has($services, $service), 404);

        return Inertia::render('services/show', [
            'service' => $services[$service],
            'services' => collect($services)->map(fn (array $item): array => Arr::only($item, [
                'slug',
                'name',
                'short_name',
            ]))->values()->all(),
            'projects' => PortfolioProject::published()
                ->where('service_slug', $service)
                ->with('images')
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderByDesc('completed_on')
                ->limit(3)
                ->get()
                ->map(PublicProjectPresenter::summary(...))
                ->values()
                ->all(),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }
}
