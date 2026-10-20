<?php

namespace App\Http\Controllers;

use App\Support\MediaLibrary;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Image preview for the Filament media page (route registered with the panel's authenticated
 * routes: administrator session required). Works without `storage:link`, never exposes the
 * stored path, and serves only safe raster images (MediaLibrary::previewResponse).
 */
class AdminMediaPreviewController extends Controller
{
    public function __invoke(string $kind, int $id): BinaryFileResponse
    {
        return MediaLibrary::previewResponse(MediaLibrary::find($kind, $id));
    }
}
