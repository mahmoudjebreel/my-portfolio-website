<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Support\PublicFileCleanup;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a project from the administration. The database removes its translations,
 * category / technology links and gallery images (cascade); afterwards the project's own
 * image files (cover and gallery) are deleted when nothing else uses them. Categories and
 * technologies themselves are kept.
 */
final class DeleteProject
{
    public function handle(Project $project): bool
    {
        $paths = $project->images()->pluck('path')->push($project->cover_image_path)->filter()->unique()->values();

        $deleted = (bool) DB::transaction(fn () => $project->delete());

        if ($deleted) {
            foreach ($paths as $path) {
                PublicFileCleanup::deleteIfUnused($path);
            }
        }

        return $deleted;
    }
}
