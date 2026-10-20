<?php

namespace App\Filament\Widgets;

use App\Enums\MessageStatus;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The five newest contact messages, linking to the message page. Texts are shown as plain
 * text by Filament's columns, never as HTML.
 */
class LatestMessages extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.latest_messages'))
            ->query(ContactMessage::query()->latest('created_at')->latest('id')->limit(5))
            ->paginated(false)
            ->emptyStateHeading(__('admin.dashboard.no_messages'))
            ->recordUrl(fn (ContactMessage $record): string => ContactMessageResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.dashboard.from'))
                    ->description(fn (ContactMessage $record): string => $record->email)
                    ->limit(40),
                TextColumn::make('subject')->label(__('admin.dashboard.subject'))->limit(50),
                TextColumn::make('status')
                    ->label(__('admin.dashboard.status'))
                    ->badge()
                    ->formatStateUsing(fn (MessageStatus $state): string => __('admin.dashboard.message_statuses.'.$state->value))
                    ->color(fn (MessageStatus $state): string => match ($state) {
                        MessageStatus::Unread => 'primary',
                        MessageStatus::Read => 'gray',
                        MessageStatus::Archived => 'warning',
                    }),
                TextColumn::make('created_at')->label(__('admin.dashboard.received'))->since()->dateTimeTooltip(),
            ]);
    }
}
