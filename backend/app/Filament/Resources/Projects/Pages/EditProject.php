<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\SaveProject;
use App\Enums\Locale;
use App\Filament\Resources\Projects\Pages\Concerns\ValidatesProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\SharedValidation;
use App\Models\Project;
use App\Support\ProjectContent;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditProject extends EditRecord
{
    use ValidatesProject;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [ProjectResource::deleteAction()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Project $project */
        $project = $this->getRecord()->load(['translations', 'categories', 'technologies']);

        $translations = [];
        foreach (Locale::cases() as $locale) {
            $translations[$locale->value] = $project->translations->first(fn ($t) => $t->locale === $locale)?->only(ProjectContent::fields())
                ?? array_fill_keys(ProjectContent::fields(), null);
        }

        return [
            'status' => $project->status->value,
            'is_featured' => (bool) $project->is_featured,
            'sort_order' => (int) $project->sort_order,
            'github_url' => $project->github_url,
            'live_url' => $project->live_url,
            'category_ids' => $project->categories->modelKeys(),
            'technology_ids' => $project->technologies->modelKeys(),
            'translations' => $translations,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Project $record */
        $validated = $this->validateProject($record, $data);

        try {
            return app(SaveProject::class)->handle($record, $validated);
        } catch (ValidationException $exception) {
            throw SharedValidation::forForm($exception);
        }
    }
}
