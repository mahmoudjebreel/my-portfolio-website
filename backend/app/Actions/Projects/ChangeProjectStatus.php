<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

/**
 * Publishes or unpublishes a project (admin API and Filament). Publishing is refused unless
 * the English and Arabic content stored for the project is complete; the first publication
 * date is set once and kept when the project goes back to draft.
 */
final class ChangeProjectStatus
{
    /**
     * @throws ValidationException when publishing incomplete content
     */
    public function handle(Project $project, ProjectStatus $status): Project
    {
        if ($status === ProjectStatus::Published) {
            SaveProject::assertPublishable($project);
        }

        $project->status = $status;
        if ($status === ProjectStatus::Published && $project->published_at === null) {
            $project->published_at = now();
        }
        $project->save();

        return $project;
    }
}
