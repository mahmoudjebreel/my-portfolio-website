<?php

namespace App\Filament\Resources\Categories;

use App\Actions\Catalog\DeleteCategory;
use App\Enums\Locale;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Support\Translated;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Project categories in the Filament panel. Saving goes through App\Actions\Catalog\SaveCategory
 * with the CategoryRequest rules. No delete: a category linked to projects must never be removed.
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.categories.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.categories.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Category ? (Translated::categoryName($record) ?? "#{$record->id}") : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations')->withCount('projects');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Grid::make(1)->columnSpan(['lg' => 2])->schema([
                    Text::make(__('admin.categories.languages_help'))->color('gray'),
                    ...array_map(fn (Locale $locale) => self::namesSection($locale), Locale::cases()),
                ]),
                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make()->schema([
                        TextInput::make('sort_order')
                            ->label(__('admin.common.sort_order'))
                            ->helperText(__('admin.common.sort_order_hint'))
                            ->numeric()->integer()->minValue(0)->maxValue(65535)
                            ->default(0)->required(),
                    ]),
                    Section::make(__('admin.common.usage'))
                        ->visible(fn (?Category $record): bool => $record !== null)
                        ->schema([
                            Text::make(fn (?Category $record): string => trans_choice('admin.common.linked_projects', $record?->projects()->count() ?? 0)),
                            Text::make(__('admin.common.no_delete'))->color('gray'),
                        ]),
                ]),
            ]),
        ]);
    }

    private static function namesSection(Locale $locale): Section
    {
        $prefix = "translations.{$locale->value}";
        $language = $locale === Locale::En ? __('admin.common.english') : __('admin.common.arabic');

        return Section::make(__('admin.categories.names', ['language' => $language]))
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                TextInput::make("{$prefix}.name")
                    ->label(__('admin.categories.name'))
                    ->maxLength(80)
                    ->extraInputAttributes(['dir' => $locale === Locale::Ar ? 'rtl' : 'ltr', 'lang' => $locale->value]),
                TextInput::make("{$prefix}.slug")
                    ->label(__('admin.categories.slug'))
                    ->maxLength(100)
                    ->helperText(__('admin.common.slug_hint'))
                    ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                    ->hintAction(
                        Action::make("generate-slug-{$locale->value}")
                            ->label(__('admin.common.generate_slug_from_name'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->action(function (Get $get, Set $set) use ($prefix): void {
                                $slug = Str::slug((string) $get("{$prefix}.name")) ?: Str::slug((string) $get('translations.en.name'));
                                if ($slug !== '') {
                                    $set("{$prefix}.slug", Str::limit($slug, 100, ''));
                                }
                            }),
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = fn (Locale $locale) => TextColumn::make("name_{$locale->value}")
            ->label($locale === Locale::En ? __('admin.categories.english_name') : __('admin.categories.arabic_name'))
            ->state(fn (Category $record): string => $record->translations->first(fn ($t) => $t->locale === $locale)?->name ?? __('admin.common.not_translated'))
            ->description(fn (Category $record): ?string => $record->translations->first(fn ($t) => $t->locale === $locale)?->slug)
            ->color(fn (Category $record): ?string => $record->translations->contains(fn ($t) => $t->locale === $locale) ? null : 'gray');

        return $table
            ->columns([
                $name(Locale::En)->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'translations',
                    fn (Builder $translation) => $translation->where('name', 'like', '%'.addcslashes($search, '\\%_').'%'),
                )),
                $name(Locale::Ar),
                TextColumn::make('projects_count')->label(__('admin.common.projects_count'))->sortable()->badge()->color('gray'),
                TextColumn::make('sort_order')->label(__('admin.common.sort_order'))->sortable(),
                TextColumn::make('updated_at')->label(__('admin.common.updated_at'))->since()->dateTimeTooltip()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), self::deleteAction()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (DeleteBulkAction $action, Collection $records, DeleteCategory $delete): void {
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
     * Deletes the record (DeleteCategory); refused while it is linked to a project.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(fn (Category $record, DeleteCategory $delete): bool => $delete->handle($record))
            ->failureNotificationTitle(__('admin.common.delete_blocked'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
