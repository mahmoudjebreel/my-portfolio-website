<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    /**
     * No "create" action: messages only come from the contact form.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
