<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a file from the public disk after the record that used it was deleted — but only
 * when the stored path is a safe relative path and no other record (project cover, gallery
 * image, image site setting) still points to it. Anything else is left untouched.
 */
final class PublicFileCleanup
{
    public static function deleteIfUnused(?string $path): bool
    {
        if (! is_string($path) || ($path = str_replace('\\', '/', trim($path))) === '') {
            return false;
        }
        if (str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, ':')) {
            return false;
        }

        $stillUsed = Project::query()->where('cover_image_path', $path)->exists()
            || ProjectImage::query()->where('path', $path)->exists()
            || SiteSetting::query()->whereIn('key', SiteSettingsForm::imageKeys())->get()->contains(fn (SiteSetting $s) => $s->value === $path);
        if ($stillUsed) {
            return false;
        }

        $disk = Storage::disk(MediaLibrary::DISK);

        return $disk->exists($path) && $disk->delete($path);
    }
}
