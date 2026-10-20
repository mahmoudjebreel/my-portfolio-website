<?php

namespace App\Actions\Projects;

use App\Enums\Locale;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProjectContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a project with its content per language and its categories and
 * technologies. Shared by the admin API and the Filament panel; callers validate the input
 * first (ProjectRequest rules). The action still enforces the business rules itself:
 *
 * - publishing requires complete English and Arabic content (checked on the saved result,
 *   so existing translations count);
 * - an existing translation is never removed or emptied (an empty language is skipped);
 * - published_at is set on the first publication only and kept afterwards;
 * - cover_image_path and other columns are never taken from the input;
 * - categories / technologies are synced only when their key is present.
 */
final class SaveProject
{
    /**
     * @param  array{
     *     status: string|ProjectStatus,
     *     is_featured?: bool,
     *     sort_order?: int,
     *     github_url?: string|null,
     *     live_url?: string|null,
     *     category_ids?: list<int>,
     *     technology_ids?: list<int>,
     *     translations?: array<string, array<string, mixed>|null>,
     * }  $data
     *
     * @throws ValidationException when publishing incomplete content
     */
    public function handle(?Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $creating = $project === null;
            $project ??= new Project;

            $status = $data['status'] instanceof ProjectStatus ? $data['status'] : ProjectStatus::from($data['status']);
            $project->status = $status;
            $project->is_featured = array_key_exists('is_featured', $data) ? (bool) $data['is_featured'] : (bool) ($project->is_featured ?? false);
            $project->sort_order = array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : (int) ($project->sort_order ?? 0);
            $project->github_url = $data['github_url'] ?? null;
            $project->live_url = $data['live_url'] ?? null;

            // First publication date; kept when a project is unpublished and published again.
            if ($status === ProjectStatus::Published && $project->published_at === null) {
                $project->published_at = now();
            }

            $project->save();

            foreach (Locale::cases() as $locale) {
                $content = $data['translations'][$locale->value] ?? null;
                if (! is_array($content) || ! ProjectContent::isFilled($content)) {
                    continue; // never removes or empties an existing translation
                }

                $values = [];
                foreach (ProjectContent::fields() as $field) {
                    $values[$field] = $content[$field] ?? null;
                }
                $project->translations()->updateOrCreate(['locale' => $locale->value], $values);
            }

            if (array_key_exists('category_ids', $data)) {
                $project->categories()->sync($data['category_ids'] ?? []);
            }
            if (array_key_exists('technology_ids', $data)) {
                $project->technologies()->sync($data['technology_ids'] ?? []);
            }

            if ($status === ProjectStatus::Published) {
                self::assertPublishable($project);
            }

            if (! $creating) {
                $project->touch(); // content-only changes also count as an update
            }

            return $project;
        });
    }

    /**
     * Refuses publication unless every language is complete in the database.
     *
     * @throws ValidationException
     */
    public static function assertPublishable(Project $project): void
    {
        $byLocale = [];
        foreach (Locale::cases() as $locale) {
            $byLocale[$locale->value] = $project->translations()->where('locale', $locale->value)->first()?->only(ProjectContent::fields());
        }

        $missing = ProjectContent::incompleteLocales($byLocale);
        if ($missing === []) {
            return;
        }

        $messages = ['status' => __('To publish, complete the English and Arabic content.')];
        foreach ($missing as $locale) {
            $messages["translations.{$locale}"] = __('This language is incomplete.');
        }

        throw ValidationException::withMessages($messages);
    }
}
