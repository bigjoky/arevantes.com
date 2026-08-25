<?php

namespace App\Support;

use App\Models\PortfolioProject;
use App\Models\PortfolioProjectImage;

class PublicProjectPresenter
{
    /** @return array<string, mixed> */
    public static function summary(PortfolioProject $project): array
    {
        $image = $project->images->first();

        return [
            'title' => $project->title,
            'slug' => $project->slug,
            'service_slug' => $project->service_slug,
            'service_name' => config("public_services.{$project->service_slug}.short_name", ucfirst($project->service_slug)),
            'location' => $project->location,
            'summary' => $project->summary,
            'completed_on' => $project->completed_on?->toDateString(),
            'completed_year' => $project->completed_on?->format('Y'),
            'href' => route('projects.show', $project->slug, absolute: false),
            'image' => $image ? self::image($image) : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(PortfolioProject $project): array
    {
        return [
            ...self::summary($project),
            'description' => $project->description,
            'images' => $project->images->map(self::image(...))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function image(PortfolioProjectImage $image): array
    {
        $url = route('project-images.show', $image, absolute: false);

        return [
            'id' => $image->id,
            'url' => $url,
            'absolute_url' => rtrim((string) config('app.url'), '/').$url,
            'alt' => $image->alt_text,
            'caption' => $image->caption,
            'width' => $image->width,
            'height' => $image->height,
            'position' => $image->position,
        ];
    }
}
