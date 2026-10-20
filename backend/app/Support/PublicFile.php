<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Builds URLs for files on the "public" disk. A URL is returned only when the
 * stored path is a safe relative path AND the file actually exists, so the API
 * never hands out broken or arbitrary links.
 */
final class PublicFile
{
    public static function url(mixed $path): ?string
    {
        if (! is_string($path) || ($path = trim($path)) === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, ':')) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        // Relative URL: works on the same origin in production and through the Vite proxy in development.
        return '/storage/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
