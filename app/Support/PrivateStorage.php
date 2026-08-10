<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class PrivateStorage
{
    public static function diskName(): string
    {
        return (string) config('filesystems.private', 'local');
    }

    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(self::diskName());
    }

    public static function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $marker = '/knowledge/';
        $position = strpos($normalized, $marker);

        return $position === false
            ? ltrim($normalized, '/')
            : substr($normalized, $position + 1);
    }

    public static function isManagedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, 'knowledge/')
            || str_contains($normalized, '/storage/app/private/knowledge/');
    }

    public static function exists(string $path): bool
    {
        return is_file($path)
            || (self::isManagedPath($path) && self::disk()->exists(self::normalizePath($path)));
    }

    public static function get(string $path): string
    {
        if (is_file($path)) {
            return File::get($path);
        }

        return self::disk()->get(self::normalizePath($path));
    }

    public static function put(string $path, string $contents): bool
    {
        return self::disk()->put(self::normalizePath($path), $contents);
    }

    public static function delete(string $path): bool
    {
        if (is_file($path) && self::isManagedPath($path)) {
            return File::delete($path);
        }

        return self::isManagedPath($path)
            ? self::disk()->delete(self::normalizePath($path))
            : false;
    }

    public static function checksum(string $path): string
    {
        return hash('sha256', self::get($path));
    }
}
