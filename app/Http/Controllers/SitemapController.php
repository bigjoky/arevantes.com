<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $pages = [
            ['path' => '/', 'priority' => '1.0', 'frequency' => 'weekly'],
            ['path' => '/servicios/energia', 'priority' => '0.8', 'frequency' => 'monthly'],
            ['path' => '/servicios/electricidad', 'priority' => '0.8', 'frequency' => 'monthly'],
            ['path' => '/servicios/seguridad', 'priority' => '0.8', 'frequency' => 'monthly'],
            ['path' => '/proyectos', 'priority' => '0.8', 'frequency' => 'weekly'],
            ['path' => '/aviso-legal', 'priority' => '0.2', 'frequency' => 'yearly'],
            ['path' => '/privacidad', 'priority' => '0.2', 'frequency' => 'yearly'],
            ['path' => '/cookies', 'priority' => '0.2', 'frequency' => 'yearly'],
        ];

        $projectPages = PortfolioProject::query()
            ->published()
            ->orderByDesc('updated_at')
            ->get(['slug'])
            ->map(fn (PortfolioProject $project): array => [
                'path' => '/proyectos/'.$project->slug,
                'priority' => '0.7',
                'frequency' => 'monthly',
            ])
            ->all();
        $pages = [...$pages, ...$projectPages];

        $urls = collect($pages)->map(function (array $page) use ($baseUrl): string {
            $location = htmlspecialchars($baseUrl.$page['path'], ENT_XML1);

            return <<<XML
                <url>
                    <loc>{$location}</loc>
                    <changefreq>{$page['frequency']}</changefreq>
                    <priority>{$page['priority']}</priority>
                </url>
            XML;
        })->implode("\n");

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            {$urls}
            </urlset>
            XML;

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
