<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Actions\Catalog\SaveCategory;
use App\Enums\Locale;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\Concerns\ValidatesCategory;
use App\Models\Category;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategory extends EditRecord
{
    use ValidatesCategory;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CategoryResource::deleteAction()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Category $category */
        $category = $this->getRecord()->load('translations');

        $translations = [];
        foreach (Locale::cases() as $locale) {
            $translation = $category->translations->first(fn ($t) => $t->locale === $locale);
            $translations[$locale->value] = ['name' => $translation?->name, 'slug' => $translation?->slug];
        }

        return ['sort_order' => (int) $category->sort_order, 'translations' => $translations];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Category $record */
        return app(SaveCategory::class)->handle($record, $this->validateCategory($record, $data));
    }
}
