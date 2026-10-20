<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\SaveProject;
use App\Filament\Resources\Projects\Pages\Concerns\ValidatesProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\SharedValidation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateProject extends CreateRecord
{
    use ValidatesProject;

    protected static string $resource = ProjectResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $validated = $this->validateProject(null, $data);

        try {
            return app(SaveProject::class)->handle(null, $validated);
        } catch (ValidationException $exception) {
            throw SharedValidation::forForm($exception);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
