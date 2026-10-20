<?php

namespace App\Filament\Resources\Technologies\Pages;

use App\Actions\Catalog\SaveTechnology;
use App\Enums\Locale;
use App\Filament\Resources\Technologies\Pages\Concerns\ValidatesTechnology;
use App\Filament\Resources\Technologies\TechnologyResource;
use App\Models\Technology;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTechnology extends EditRecord
{
    use ValidatesTechnology;

    protected static string $resource = TechnologyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TechnologyResource::visibilityAction()->after(fn () => $this->refreshFormData([])),
            TechnologyResource::deleteAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Technology $technology */
        $technology = $this->getRecord()->load('translations');

        $translations = [];
        foreach (Locale::cases() as $locale) {
            $translation = $technology->translations->first(fn ($t) => $t->locale === $locale);
            $translations[$locale->value] = ['display_name' => $translation?->display_name, 'description' => $translation?->description];
        }

        return [
            'name' => $technology->name,
            'slug' => $technology->slug,
            'group' => $technology->group->value,
            'icon' => $technology->icon,
            'sort_order' => (int) $technology->sort_order,
            'translations' => $translations,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Technology $record */
        return app(SaveTechnology::class)->handle($record, $this->validateTechnology($record, $data));
    }
}
