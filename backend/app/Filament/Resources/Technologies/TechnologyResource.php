<?php

namespace App\Filament\Resources\Technologies;

use App\Actions\Catalog\ChangeTechnologyVisibility;
use App\Actions\Catalog\DeleteTechnology;
use App\Enums\Locale;
use App\Enums\TechnologyGroup;
use App\Filament\Resources\Technologies\Pages\CreateTechnology;
use App\Filament\Resources\Technologies\Pages\EditTechnology;
use App\Filament\Resources\Technologies\Pages\ListTechnologies;
use App\Models\Technology;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Technologies in the Filament panel. Saving goes through App\Actions\Catalog\SaveTechnology
 * with the TechnologyRequest rules. Visibility on the public site is chosen only when creating
 * (hidden by default); editing never changes it — only the explicit show / hide button does.
 * Deleting is refused while the technology is linked to a project.
 */
class TechnologyResource extends Resource
{
    protected static ?string $model = Technology::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.technologies.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.technologies.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Technology ? $record->name : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations')->withCount('projects');
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        $options = [];
        foreach (TechnologyGroup::cases() as $group) {
            $options[$group->value] = __('admin.technologies.groups.'.$group->value);
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Grid::make(1)->columnSpan(['lg' => 2])->schema([
                    Section::make(__('admin.technologies.details'))
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            TextInput::make('name')
                                ->label(__('admin.technologies.name'))
                                ->helperText(__('admin.technologies.name_hint'))
                                ->maxLength(60)->required()
                                ->extraInputAttributes(['dir' => 'ltr']),
                            TextInput::make('slug')
                                ->label(__('admin.technologies.slug'))
                                ->helperText(__('admin.common.slug_hint'))
                                ->maxLength(80)->required()
                                ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                                ->hintAction(
                                    Action::make('generate-slug')
                                        ->label(__('admin.common.generate_slug_from_name'))
                                        ->icon(Heroicon::OutlinedSparkles)
                                        ->action(function (Get $get, Set $set): void {
                                            $slug = Str::slug((string) $get('name'));
                                            if ($slug !== '') {
                                                $set('slug', Str::limit($slug, 80, ''));
                                            }
                                        }),
                                ),
                            Select::make('group')
                                ->label(__('admin.technologies.group'))
                                ->options(self::groupOptions())
                                ->required()
                                ->native(false),
                            TextInput::make('icon')
                                ->label(__('admin.technologies.icon'))
                                ->helperText(__('admin.technologies.icon_hint'))
                                ->maxLength(60)
                                ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
                        ]),
                    ...array_map(fn (Locale $locale) => self::textsSection($locale), Locale::cases()),
                ]),
                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make(__('admin.technologies.visibility'))->schema([
                        Toggle::make('is_visible')
                            ->label(__('admin.technologies.show_on_site'))
                            ->default(false)
                            ->visibleOn('create'),
                        Text::make(__('admin.technologies.visibility_new'))->color('gray')->visibleOn('create'),
                        Text::make(fn (?Technology $record): string => $record?->is_visible ? __('admin.technologies.visible') : __('admin.technologies.hidden'))
                            ->badge()
                            ->color(fn (?Technology $record): string => $record?->is_visible ? 'success' : 'gray')
                            ->visibleOn('edit'),
                        Text::make(__('admin.technologies.visibility_locked'))->color('gray')->visibleOn('edit'),
                    ]),
                    Section::make()->schema([
                        TextInput::make('sort_order')
                            ->label(__('admin.common.sort_order'))
                            ->helperText(__('admin.common.sort_order_hint'))
                            ->numeric()->integer()->minValue(0)->maxValue(65535)
                            ->default(0)->required(),
                    ]),
                    Section::make(__('admin.common.usage'))
                        ->visibleOn('edit')
                        ->schema([
                            Text::make(fn (?Technology $record): string => trans_choice('admin.common.linked_projects', $record?->projects()->count() ?? 0)),
                            Text::make(__('admin.common.no_delete'))->color('gray'),
                        ]),
                ]),
            ]),
        ]);
    }

    private static function textsSection(Locale $locale): Section
    {
        $prefix = "translations.{$locale->value}";
        $language = $locale === Locale::En ? __('admin.common.english') : __('admin.common.arabic');
        $direction = ['dir' => $locale === Locale::Ar ? 'rtl' : 'ltr', 'lang' => $locale->value];

        return Section::make(__('admin.technologies.texts', ['language' => $language]))
            ->description(__('admin.technologies.texts_help'))
            ->collapsible()
            ->schema([
                TextInput::make("{$prefix}.display_name")
                    ->label(__('admin.technologies.display_name'))
                    ->maxLength(60)
                    ->extraInputAttributes($direction),
                Textarea::make("{$prefix}.description")
                    ->label(__('admin.technologies.description'))
                    ->maxLength(300)->rows(3)
                    ->extraInputAttributes($direction),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.technologies.name'))
                    ->description(fn (Technology $record): string => $record->slug)
                    ->weight('medium')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = '%'.addcslashes($search, '\\%_').'%';

                        return $query->where(fn (Builder $match) => $match
                            ->where('name', 'like', $like)
                            ->orWhereHas('translations', fn (Builder $translation) => $translation->where('display_name', 'like', $like)));
                    }),
                TextColumn::make('group')
                    ->label(__('admin.technologies.group'))
                    ->formatStateUsing(fn (TechnologyGroup $state): string => __('admin.technologies.groups.'.$state->value))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('icon')
                    ->label(__('admin.technologies.icon'))
                    ->fontFamily('mono')
                    ->placeholder(__('admin.technologies.no_icon')),
                IconColumn::make('is_visible')
                    ->label(__('admin.technologies.visibility'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedEye)
                    ->falseIcon(Heroicon::OutlinedEyeSlash)
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('projects_count')->label(__('admin.common.projects_count'))->sortable()->badge()->color('gray'),
                TextColumn::make('updated_at')->label(__('admin.common.updated_at'))->since()->dateTimeTooltip()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('group')->label(__('admin.technologies.group'))->options(self::groupOptions()),
                TernaryFilter::make('is_visible')
                    ->label(__('admin.technologies.visibility'))
                    ->trueLabel(__('admin.technologies.visible'))
                    ->falseLabel(__('admin.technologies.hidden')),
            ])
            ->recordActions([EditAction::make(), self::visibilityAction(), self::deleteAction()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (DeleteBulkAction $action, Collection $records, DeleteTechnology $delete): void {
                            foreach ($records as $record) {
                                if (! $delete->handle($record)) {
                                    $action->reportBulkProcessingFailure();
                                }
                            }
                        })
                        ->failureNotificationTitle(__('admin.common.delete_blocked_some')),
                ]),
            ]);
    }

    /**
     * Deletes the record (DeleteTechnology); refused while it is linked to a project.
     */
    /**
     * Explicit show / hide on the public website (ChangeTechnologyVisibility). Editing the
     * details never changes visibility; only this button does.
     */
    public static function visibilityAction(): Action
    {
        return Action::make('toggleVisibility')
            ->label(fn (Technology $record): string => $record->is_visible ? __('admin.technologies.hide_on_site') : __('admin.technologies.show_on_site_action'))
            ->icon(fn (Technology $record) => $record->is_visible ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
            ->color(fn (Technology $record): string => $record->is_visible ? 'gray' : 'success')
            ->requiresConfirmation()
            ->action(function (Technology $record, ChangeTechnologyVisibility $change): void {
                $change->handle($record, ! $record->is_visible);
                Notification::make()->success()
                    ->title($record->is_visible ? __('admin.technologies.now_visible') : __('admin.technologies.now_hidden'))
                    ->send();
            });
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(fn (Technology $record, DeleteTechnology $delete): bool => $delete->handle($record))
            ->failureNotificationTitle(__('admin.common.delete_blocked'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTechnologies::route('/'),
            'create' => CreateTechnology::route('/create'),
            'edit' => EditTechnology::route('/{record}/edit'),
        ];
    }
}
