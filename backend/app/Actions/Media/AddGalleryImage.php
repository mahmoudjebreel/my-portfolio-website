<?php

namespace App\Actions\Media;

use App\Enums\Locale;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Support\WebpImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Adds one gallery image to a project (admin API and Filament).
 *
 * - The bytes are decoded and re-encoded to WebP (WebpImage: JPG / PNG / WebP only, 5 MB,
 *   size limits); anything else is refused with a validation error on `file`.
 * - The stored name is generated here (projects/{id}/{uuid}.webp); nothing from the client
 *   is used as a path, and no existing file is replaced or deleted.
 * - The file is written last inside the transaction: if writing fails, no row is kept.
 */
final class AddGalleryImage
{
    public const DISK = 'public';

    /**
     * @param  array<string, string|null>  $alt  optional alternative text per language
     *
     * @throws ValidationException when the file is not an acceptable image
     */
    public function handle(Project $project, string $bytes, array $alt = []): ProjectImage
    {
        try {
            $encoded = WebpImage::encode($bytes);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => __($exception->getMessage())]);
        }

        $disk = Storage::disk(self::DISK);

        return DB::transaction(function () use ($project, $alt, $encoded, $disk) {
            do {
                $path = "projects/{$project->id}/".Str::uuid()->toString().'.webp';
            } while ($disk->exists($path));

            $image = $project->images()->create([
                'path' => $path,
                'width' => $encoded['width'],
                'height' => $encoded['height'],
                'sort_order' => min(65535, (int) $project->images()->max('sort_order') + 1),
            ]);

            foreach (Locale::cases() as $locale) {
                $text = trim((string) ($alt[$locale->value] ?? ''));
                if ($text !== '') {
                    $image->translations()->create(['locale' => $locale->value, 'alt_text' => $text]);
                }
            }

            if (! $disk->put($path, $encoded['bytes'])) {
                throw new RuntimeException('The image could not be stored.');
            }

            return $image;
        });
    }
}
