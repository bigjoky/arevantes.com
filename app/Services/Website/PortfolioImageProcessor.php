<?php

namespace App\Services\Website;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PortfolioImageProcessor
{
    /** @return array{disk: string, path: string, mime_type: string, size: int, width: int, height: int} */
    public function store(UploadedFile $file): array
    {
        try {
            $source = match ($file->getMimeType()) {
                'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
                'image/png' => imagecreatefrompng($file->getRealPath()),
                'image/webp' => imagecreatefromwebp($file->getRealPath()),
                default => false,
            };

            if ($source === false) {
                throw new \RuntimeException('The image could not be decoded.');
            }

            $source = $this->orient($source, $file);
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $scale = min(1, 2200 / max($sourceWidth, $sourceHeight));
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));
            $target = imagecreatetruecolor($width, $height);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

            ob_start();
            $encoded = imagewebp($target, null, 82);
            $contents = ob_get_clean();
            imagedestroy($source);
            imagedestroy($target);

            if (! $encoded || ! is_string($contents)) {
                throw new \RuntimeException('The image could not be encoded.');
            }

            $disk = (string) config('filesystems.private', 'local');
            $path = 'portfolio/'.now()->format('Y/m').'/'.Str::uuid().'.webp';
            Storage::disk($disk)->put($path, $contents);

            return [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => 'image/webp',
                'size' => strlen($contents),
                'width' => $width,
                'height' => $height,
            ];
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'images' => __('No se ha podido procesar una de las imágenes. Usa JPG, PNG o WebP.'),
            ]);
        }
    }

    private function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => imagerotate($this->flip($image, IMG_FLIP_HORIZONTAL), -90, 0),
            6 => imagerotate($image, -90, 0),
            7 => imagerotate($this->flip($image, IMG_FLIP_HORIZONTAL), 90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function flip(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }
}
