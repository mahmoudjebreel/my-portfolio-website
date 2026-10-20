<?php

namespace App\Filament\Resources\Technologies\Pages;

use App\Actions\Catalog\SaveTechnology;
use App\Filament\Resources\Technologies\Pages\Concerns\ValidatesTechnology;
use App\Filament\Resources\Technologies\TechnologyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTechnology extends CreateRecord
{
    use ValidatesTechnology;

    protected static string $resource = TechnologyResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTechnology::class)->handle(null, $this->validateTechnology(null, $data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
