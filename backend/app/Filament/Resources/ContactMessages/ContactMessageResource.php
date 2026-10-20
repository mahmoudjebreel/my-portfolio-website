<?php

namespace App\Filament\Resources\ContactMessages;

use App\Actions\Messages\ChangeMessageStatus;
use App\Enums\MessageStatus;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Support\ReplyLink;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contact inbox in the Filament panel. Read-only apart from marking read / unread
 * (App\Actions\Messages\ChangeMessageStatus). Texts are shown as plain text (escaped, line
 * breaks kept), never as HTML. No create or edit; messages can be deleted. No email is ever sent.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin.messages.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.messages.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.inbox');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.messages.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ChangeMessageStatus::unreadCount();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof ContactMessage ? ($record->subject ?: __('admin.messages.no_subject')) : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function statusLabel(MessageStatus $status): string
    {
        return __('admin.dashboard.message_statuses.'.$status->value);
    }

    public static function statusColor(MessageStatus $status): string
    {
        return match ($status) {
            MessageStatus::Unread => 'primary',
            MessageStatus::Read => 'gray',
            MessageStatus::Archived => 'warning',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Section::make(fn (ContactMessage $record): string => $record->subject ?: __('admin.messages.no_subject'))
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        TextEntry::make('message')
                            ->label(__('admin.messages.fields.message'))
                            ->hiddenLabel()
                            ->extraAttributes(['dir' => 'auto', 'style' => 'white-space: pre-wrap; overflow-wrap: anywhere;']),
                    ]),
                Section::make()->columnSpan(['lg' => 1])->schema([
                    TextEntry::make('status')
                        ->label(__('admin.messages.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (MessageStatus $state): string => self::statusLabel($state))
                        ->color(fn (MessageStatus $state): string => self::statusColor($state)),
                    TextEntry::make('name')->label(__('admin.messages.fields.name'))->extraAttributes(['dir' => 'auto']),
                    TextEntry::make('email')->label(__('admin.messages.fields.email'))->copyable()->extraAttributes(['dir' => 'ltr']),
                    TextEntry::make('created_at')->label(__('admin.messages.fields.received'))->dateTime(),
                    TextEntry::make('read_at')->label(__('admin.messages.fields.read_at'))->dateTime()->placeholder('—'),
                    TextEntry::make('notified_at')
                        ->label(__('admin.messages.fields.notified_at'))
                        ->dateTime()
                        ->placeholder(__('admin.messages.not_notified')),
                    TextEntry::make('locale')
                        ->label(__('admin.messages.fields.language'))
                        ->formatStateUsing(fn ($state): string => __('admin.messages.languages.'.($state?->value ?? $state)))
                        ->placeholder('—'),
                    Text::make(fn (ContactMessage $record): string => ReplyLink::for($record->email, $record->subject) ? __('admin.messages.reply_note') : __('admin.messages.no_reply'))
                        ->color('gray'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.dashboard.from'))
                    ->description(fn (ContactMessage $record): string => $record->email)
                    ->weight(fn (ContactMessage $record): ?string => $record->status === MessageStatus::Unread ? 'bold' : null)
                    ->limit(40),
                TextColumn::make('subject')
                    ->label(__('admin.messages.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state ?: __('admin.messages.no_subject'))
                    ->description(fn (ContactMessage $record): string => str((string) $record->message)->squish()->limit(90)->toString())
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('admin.messages.fields.received'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.messages.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (MessageStatus $state): string => self::statusLabel($state))
                    ->color(fn (MessageStatus $state): string => self::statusColor($state)),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('created_at')->orderByDesc('id'))
            // New messages from the website appear without reloading the page.
            ->poll('15s')
            ->searchable()
            ->searchUsing(function (Builder $query, string $search): Builder {
                $like = '%'.addcslashes($search, '\\%_').'%';

                return $query->where(fn (Builder $match) => $match
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('subject', 'like', $like));
            })
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.messages.fields.status'))
                    ->options(collect(MessageStatus::cases())->mapWithKeys(fn (MessageStatus $status) => [$status->value => self::statusLabel($status)])->all()),
            ])
            ->recordUrl(fn (ContactMessage $record): string => self::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                self::toggleReadAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    /**
     * Mark read / unread (hidden for archived messages).
     */
    public static function toggleReadAction(): Action
    {
        return Action::make('toggleRead')
            ->label(fn (ContactMessage $record): string => $record->status === MessageStatus::Unread ? __('admin.messages.mark_read') : __('admin.messages.mark_unread'))
            ->icon(fn (ContactMessage $record) => $record->status === MessageStatus::Unread ? Heroicon::OutlinedEnvelopeOpen : Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (ContactMessage $record): bool => $record->status !== MessageStatus::Archived)
            ->action(function (ContactMessage $record, ChangeMessageStatus $change): void {
                $target = $record->status === MessageStatus::Unread ? MessageStatus::Read : MessageStatus::Unread;
                try {
                    $change->handle($record, $target);
                } catch (DomainException) {
                    Notification::make()->danger()->title(__('admin.messages.archived_locked'))->send();

                    return;
                }
                Notification::make()->success()
                    ->title($target === MessageStatus::Read ? __('admin.messages.marked_read') : __('admin.messages.marked_unread'))
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
