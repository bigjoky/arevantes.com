<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProjectImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioProjectImageController extends Controller
{
    public function __invoke(Request $request, PortfolioProjectImage $portfolioProjectImage): BinaryFileResponse|StreamedResponse
    {
        $portfolioProjectImage->loadMissing('project');
        $isPublished = $portfolioProjectImage->project->published_at?->isPast() ?? false;
        $canPreview = $request->user()?->can('manage-admin') ?? false;
        abort_unless($isPublished || $canPreview, 404);

        $headers = [
            'Cache-Control' => $isPublished ? 'public, max-age=31536000, immutable' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($portfolioProjectImage->disk === 'public_asset') {
            $path = public_path($portfolioProjectImage->path);
            abort_unless(is_file($path), 404);

            return response()->file($path, [...$headers, 'Content-Type' => $portfolioProjectImage->mime_type]);
        }

        abort_unless(Storage::disk($portfolioProjectImage->disk)->exists($portfolioProjectImage->path), 404);

        return Storage::disk($portfolioProjectImage->disk)->response(
            $portfolioProjectImage->path,
            basename($portfolioProjectImage->path),
            [...$headers, 'Content-Type' => $portfolioProjectImage->mime_type],
        );
    }
}
