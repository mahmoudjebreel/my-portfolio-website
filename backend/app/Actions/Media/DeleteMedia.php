<?php

namespace App\Actions\Media;

use App\Models\Project;
use App\Models\ProjectImage;
use App\Support\PublicFileCleanup;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an image from the media library:
 * - gallery: the project_images row (and its alt texts), then the file if nothing else uses it;
 * - cover: the project's cover is cleared (the project itself is kept), then the file if
 *   nothing else uses it.
 */
final class DeleteMedia
{
    public function handle(string $kind, int $id): bool
    {
        if ($kind === 'gallery') {
            $image = ProjectImage::query()->find($id);
            if ($image === null) {
                return false;
            }
            $path = $image->path;
            DB::transaction(fn () => $image->delete());
            PublicFileCleanup::deleteIfUnused($path);

            return true;
        }

        if ($kind === 'cover') {
            $project = Project::query()->find($id);
            if ($project === null || blank($project->cover_image_path)) {
                return false;
            }
            $path = $project->cover_image_path;
            $project->cover_image_path = null;
            $project->save();
            PublicFileCleanup::deleteIfUnused($path);

            return true;
        }

        return false;
    }
}
