<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Actions\Catalog\SaveCategory;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\Concerns\ValidatesCategory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategory extends CreateRecord
{
    use ValidatesCategory;

    protected static string $resource = CategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveCategory::class)->handle(null, $this->validateCategory(null, $data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
