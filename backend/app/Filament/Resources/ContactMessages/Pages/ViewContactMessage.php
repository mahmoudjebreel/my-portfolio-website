<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\ReplyLink;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One contact message. Opening it does not change it; only the read / unread action does.
 */
class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label(__('admin.messages.reply'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->url(fn (ContactMessage $record): ?string => ReplyLink::for($record->email, $record->subject))
                ->visible(fn (ContactMessage $record): bool => ReplyLink::for($record->email, $record->subject) !== null),
            ContactMessageResource::toggleReadAction()
                ->after(fn () => $this->refreshFormData(['status', 'read_at'])),
            DeleteAction::make(),
        ];
    }
}
