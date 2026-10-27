<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\PortfolioProject;
use App\Models\PortfolioProjectImage;
use App\Rules\SafeUpload;
use App\Services\Website\PortfolioImageProcessor;
use App\Support\PublicProjectPresenter;
use App\Support\Time\BusinessTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PortfolioProjectAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('website/projects/index', [
            'projects' => PortfolioProject::query()
                ->with(['images', 'installation:id,project_reference,site_name'])
                ->orderByDesc('published_at')
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (PortfolioProject $project): array => $this->serialize($project)),
            'installations' => Installation::query()
                ->orderByDesc('completed_date')
                ->orderByDesc('id')
                ->limit(250)
                ->get(['id', 'project_reference', 'site_name', 'site_city', 'service_type'])
                ->map(fn (Installation $installation): array => [
                    'id' => $installation->id,
                    'label' => $installation->project_reference ?: ($installation->site_name ?: "Proyecto #{$installation->id}"),
                    'location' => $installation->site_city,
                    'service_type' => $installation->service_type,
                ]),
            'services' => collect(config(app()->isLocale('en') ? 'public_services_en' : 'public_services', []))->map(fn (array $service): array => [
                'slug' => $service['slug'],
                'name' => $service['name'],
            ])->values(),
        ]);
    }

    public function store(Request $request, PortfolioImageProcessor $processor): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data['published'] && ! $request->hasFile('images')) {
            throw ValidationException::withMessages(['images' => __('Añade al menos una imagen antes de publicar el proyecto.')]);
        }

        $stored = $this->processUploads($request, $processor);

        try {
            DB::transaction(function () use ($data, $stored, $request): void {
                $project = PortfolioProject::create([
                    ...$this->attributes($data),
                    'slug' => $this->uniqueSlug($data['title']),
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                $this->attachImages($project, $stored, $request);
            });
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Proyecto creado correctamente.')]);
    }

    public function update(Request $request, PortfolioProject $portfolioProject, PortfolioImageProcessor $processor): RedirectResponse
    {
        $data = $this->validated($request, $portfolioProject);
        $newImageCount = count($request->file('images', []));
        if ($data['published'] && $portfolioProject->images()->count() + $newImageCount === 0) {
            throw ValidationException::withMessages(['images' => __('Añade al menos una imagen antes de publicar el proyecto.')]);
        }

        $stored = $this->processUploads($request, $processor);

        try {
            DB::transaction(function () use ($data, $stored, $request, $portfolioProject): void {
                $portfolioProject->update([
                    ...$this->attributes($data, $portfolioProject),
                    'updated_by' => $request->user()->id,
                ]);

                foreach ($data['existing_images'] ?? [] as $imageData) {
                    $image = $portfolioProject->images()->findOrFail($imageData['id']);
                    $image->update([
                        'alt_text' => $imageData['alt_text'],
                        'caption' => $imageData['caption'] ?? null,
                        'position' => $imageData['position'],
                    ]);
                }

                $this->attachImages($portfolioProject, $stored, $request);
            });
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Proyecto actualizado correctamente.')]);
    }

    public function destroy(PortfolioProject $portfolioProject): RedirectResponse
    {
        $portfolioProject->load('images');
        foreach ($portfolioProject->images as $image) {
            $this->deleteImageFile($image);
        }
        $portfolioProject->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Proyecto eliminado.')]);
    }

    public function destroyImage(PortfolioProjectImage $portfolioProjectImage): RedirectResponse
    {
        $portfolioProjectImage->loadMissing('project');
        if ($portfolioProjectImage->project->published_at && $portfolioProjectImage->project->images()->count() <= 1) {
            throw ValidationException::withMessages(['images' => __('Un proyecto publicado debe conservar al menos una imagen.')]);
        }

        $this->deleteImageFile($portfolioProjectImage);
        $portfolioProjectImage->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Imagen eliminada.')]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PortfolioProject $project = null): array
    {
        return $request->validate([
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
            'title' => ['required', 'string', 'max:160'],
            'service_slug' => ['required', Rule::in(array_keys(config('public_services', [])))],
            'location' => ['nullable', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'completed_on' => ['nullable', 'date'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
            'published' => ['required', 'boolean'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['file', 'image', new SafeUpload(['jpg', 'jpeg', 'png', 'webp'], 15360), 'dimensions:max_width=12000,max_height=12000'],
            'existing_images' => ['nullable', 'array', 'max:50'],
            'existing_images.*.id' => ['required', 'integer'],
            'existing_images.*.alt_text' => ['required', 'string', 'max:255'],
            'existing_images.*.caption' => ['nullable', 'string', 'max:255'],
            'existing_images.*.position' => ['required', 'integer', 'between:0,10000'],
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?PortfolioProject $project = null): array
    {
        return [
            'installation_id' => $data['installation_id'] ?? null,
            'title' => $data['title'],
            'service_slug' => $data['service_slug'],
            'location' => $data['location'] ?? null,
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'completed_on' => $data['completed_on'] ?? null,
            'is_featured' => $data['is_featured'],
            'sort_order' => $data['sort_order'],
            'published_at' => $data['published'] ? ($project?->published_at ?? BusinessTime::now()) : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function processUploads(Request $request, PortfolioImageProcessor $processor): array
    {
        return collect($request->file('images', []))->map(fn ($file): array => $processor->store($file))->all();
    }

    /** @param array<int, array<string, mixed>> $images */
    private function attachImages(PortfolioProject $project, array $images, Request $request): void
    {
        $position = (int) $project->images()->max('position');
        foreach ($images as $image) {
            $project->images()->create([
                ...$image,
                'uploaded_by' => $request->user()->id,
                'alt_text' => trim($project->title.($project->location ? " — {$project->location}" : '')),
                'position' => $position += 10,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $images */
    private function deleteStored(array $images): void
    {
        foreach ($images as $image) {
            Storage::disk($image['disk'])->delete($image['path']);
        }
    }

    private function deleteImageFile(PortfolioProjectImage $image): void
    {
        if ($image->disk !== 'public_asset') {
            Storage::disk($image->disk)->delete($image->path);
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'proyecto';
        $slug = $base;
        $suffix = 2;
        while (PortfolioProject::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function serialize(PortfolioProject $project): array
    {
        return [
            'id' => $project->id,
            'installation_id' => $project->installation_id,
            'installation_label' => $project->installation?->project_reference ?: $project->installation?->site_name,
            'title' => $project->title,
            'slug' => $project->slug,
            'service_slug' => $project->service_slug,
            'location' => $project->location,
            'summary' => $project->summary,
            'description' => $project->description,
            'completed_on' => $project->completed_on?->toDateString(),
            'is_featured' => $project->is_featured,
            'sort_order' => $project->sort_order,
            'published' => (bool) $project->published_at,
            'published_at' => $project->published_at?->toIso8601String(),
            'public_url' => $project->published_at ? route('projects.show', $project->slug, absolute: false) : null,
            'images' => $project->images->map(PublicProjectPresenter::image(...))->values()->all(),
        ];
    }
}
