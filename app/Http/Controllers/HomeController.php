<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Support\PublicProjectPresenter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $projects = PortfolioProject::query()
            ->published()
            ->with('images')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('completed_on')
            ->limit(3)
            ->get()
            ->map(PublicProjectPresenter::summary(...));

        return Inertia::render('welcome', [
            'siteUrl' => rtrim((string) config('app.url'), '/'),
            'projects' => $projects,
        ]);
    }
}
