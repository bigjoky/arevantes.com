<?php

namespace App\Http\Controllers;

use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class PublicServiceController extends Controller
{
    public function __invoke(string $service): Response
    {
        $services = config('public_services', []);
        abort_unless(Arr::has($services, $service), 404);

        return Inertia::render('services/show', [
            'service' => $services[$service],
            'services' => collect($services)->map(fn (array $item): array => Arr::only($item, [
                'slug',
                'name',
                'short_name',
            ]))->values()->all(),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }
}
