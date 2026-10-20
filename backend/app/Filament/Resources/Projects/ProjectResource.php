<?php

namespace App\Filament\Resources\Projects;

use App\Actions\Projects\ChangeProjectStatus;
use App\Actions\Projects\DeleteProject;
use App\Enums\Locale;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Support\Translated;
use App\Models\Category;
use App\Models\Project;
use App\Models\Technology;
use App\Support\ProjectContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
use Illuminate\Validation\ValidationException;

/**
 * Projects in the Filament panel. Saving goes through App\Actions\Projects\SaveProject and
 * the ProjectRequest rules (Pages\CreateProject / EditProject); publishing through
 * ChangeProjectStatus. Publishing needs complete English and Arabic content. Deleting goes
 * through DeleteProject (translations, links, gallery rows and unused image files).
 */
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin.projects.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.projects.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Project ? (self::title($record) ?? __('admin.projects.untitled')) : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['translations', 'categories.translations']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Tabs::make('content')
                    ->columnSpan(['lg' => 2])
                    ->tabs(array_map(fn (Locale $locale) => self::contentTab($locale), Locale::cases())),

                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make(__('admin.projects.sections.publishing'))->schema([
                        ToggleButtons::make('status')
                            ->label(__('admin.projects.fields.status'))
                            ->options([
                                ProjectStatus::Draft->value => __('admin.projects.statuses.draft'),
                                ProjectStatus::Published->value => __('admin.projects.statuses.published'),
                            ])
                            ->colors([ProjectStatus::Draft->value => 'warning', ProjectStatus::Published->value => 'success'])
                            ->icons([ProjectStatus::Draft->value => Heroicon::OutlinedPencilSquare, ProjectStatus::Published->value => Heroicon::OutlinedGlobeAlt])
                            ->inline()
                            ->default(ProjectStatus::Draft->value)
                            ->required()
                            ->helperText(__('admin.projects.status_help')),
                        Toggle::make('is_featured')->label(__('admin.projects.fields.is_featured'))->default(false),
                        TextInput::make('sort_order')
                            ->label(__('admin.common.sort_order'))
                            ->helperText(__('admin.common.sort_order_hint'))
                            ->numeric()->integer()->minValue(0)->maxValue(100000)
                            ->default(0)->required(),
                    ]),
                    Section::make(__('admin.projects.sections.links'))->schema([
                        TextInput::make('live_url')->label(__('admin.projects.fields.live_url'))->url()->maxLength(500)->placeholder('https://'),
                        TextInput::make('github_url')->label(__('admin.projects.fields.github_url'))->url()->maxLength(500)->placeholder('https://'),
                    ]),
                    Section::make(__('admin.projects.sections.images'))
                        ->visible(fn (?Project $record): bool => $record !== null)
                        ->schema([
                            Text::make(fn (?Project $record): string => $record?->cover_image_path ? __('admin.projects.cover_set') : __('admin.projects.cover_missing')),
                            Text::make(fn (?Project $record): string => trans_choice('admin.projects.images_count', $record?->images()->count() ?? 0)),
                            Text::make(__('admin.projects.images_note'))->color('gray'),
                        ]),
                ]),

                Section::make(__('admin.projects.sections.classification'))
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        CheckboxList::make('category_ids')
                            ->label(__('admin.projects.fields.categories'))
                            ->options(fn (): array => Category::query()->with('translations')->orderBy('sort_order')->orderBy('id')->get()
                                ->mapWithKeys(fn (Category $category) => [$category->id => Translated::categoryName($category) ?? "#{$category->id}"])
                                ->all())
                            ->searchable()
                            ->bulkToggleable(),
                        CheckboxList::make('technology_ids')
                            ->label(__('admin.projects.fields.technologies'))
                            ->options(fn (): array => Technology::query()->orderBy('sort_order')->orderBy('name')->get()
                                ->mapWithKeys(fn (Technology $technology) => [$technology->id => sprintf(
                                    '%s · %s%s',
                                    $technology->name,
                                    __('admin.technologies.groups.'.$technology->group->value),
                                    $technology->is_visible ? '' : ' ('.__('admin.projects.hidden_technology').')',
                                )])
                                ->all())
                            ->searchable()
                            ->columns(2),
                    ]),
            ]),
        ]);
    }

    private static function contentTab(Locale $locale): Tab
    {
        $prefix = "translations.{$locale->value}";
        $rtl = $locale === Locale::Ar;
        $direction = ['dir' => $rtl ? 'rtl' : 'ltr', 'lang' => $locale->value];

        return Tab::make($locale === Locale::En ? __('admin.projects.english_content') : __('admin.projects.arabic_content'))
            ->key("content-{$locale->value}")
            ->icon(Heroicon::OutlinedLanguage)
            ->schema([
                TextInput::make("{$prefix}.title")
                    ->label(__('admin.projects.fields.title'))
                    ->maxLength(150)
                    ->extraInputAttributes($direction),
                TextInput::make("{$prefix}.slug")
                    ->label(__('admin.projects.fields.slug'))
                    ->maxLength(160)
                    ->helperText(__('admin.common.slug_hint'))
                    ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                    ->hintAction(
                        Action::make("generate-slug-{$locale->value}")
                            ->label(__('admin.common.generate_slug'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->action(function (Get $get, Set $set) use ($prefix): void {
                                $slug = Str::slug((string) $get("{$prefix}.title")) ?: Str::slug((string) $get('translations.en.title'));
                                if ($slug !== '') {
                                    $set("{$prefix}.slug", Str::limit($slug, 160, ''));
                                }
                            }),
                    ),
                Textarea::make("{$prefix}.short_description")
                    ->label(__('admin.projects.fields.short_description'))
                    ->maxLength(300)->rows(2)
                    ->extraInputAttributes($direction),
                Textarea::make("{$prefix}.description")
                    ->label(__('admin.projects.fields.description'))
                    ->maxLength(20000)->rows(6)
                    ->extraInputAttributes($direction),
                Textarea::make("{$prefix}.problem")
                    ->label(__('admin.projects.fields.problem'))
                    ->maxLength(20000)->rows(4)
                    ->extraInputAttributes($direction),
                Textarea::make("{$prefix}.solution")
                    ->label(__('admin.projects.fields.solution'))
                    ->maxLength(20000)->rows(4)
                    ->extraInputAttributes($direction),
                TextInput::make("{$prefix}.cover_image_alt")
                    ->label(__('admin.projects.fields.cover_image_alt'))
                    ->maxLength(200)
                    ->extraInputAttributes($direction),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.projects.fields.title'))
                    ->state(fn (Project $record): string => self::title($record) ?? __('admin.projects.untitled'))
                    ->description(fn (Project $record): ?string => $record->is_featured ? '★ '.__('admin.projects.featured_filter') : null)
                    ->weight('medium')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $translation) => $translation->where('title', 'like', '%'.addcslashes($search, '\\%_').'%'),
                    )),
                IconColumn::make('complete_en')
                    ->label('EN')
                    ->state(fn (Project $record): bool => ProjectContent::isComplete(self::content($record, Locale::En)))
                    ->boolean()
                    ->tooltip(fn (bool $state): string => $state ? __('admin.projects.complete') : __('admin.projects.incomplete')),
                IconColumn::make('complete_ar')
                    ->label('AR')
                    ->state(fn (Project $record): bool => ProjectContent::isComplete(self::content($record, Locale::Ar)))
                    ->boolean()
                    ->tooltip(fn (bool $state): string => $state ? __('admin.projects.complete') : __('admin.projects.incomplete')),
                TextColumn::make('categories_list')
                    ->label(__('admin.projects.fields.categories'))
                    ->state(fn (Project $record): array => $record->categories->map(fn (Category $category) => Translated::categoryName($category))->filter()->values()->all())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('admin.projects.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => __('admin.projects.statuses.'.$state->value))
                    ->color(fn (ProjectStatus $state): string => $state === ProjectStatus::Published ? 'success' : 'warning')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('admin.common.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.projects.fields.status'))
                    ->options([
                        ProjectStatus::Published->value => __('admin.projects.statuses.published'),
                        ProjectStatus::Draft->value => __('admin.projects.statuses.draft'),
                    ]),
                SelectFilter::make('category')
                    ->label(__('admin.projects.fields.categories'))
                    ->options(fn (): array => Category::query()->with('translations')->orderBy('sort_order')->get()
                        ->mapWithKeys(fn (Category $category) => [$category->id => Translated::categoryName($category) ?? "#{$category->id}"])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($data['value']))
                        : $query),
                TernaryFilter::make('is_featured')->label(__('admin.projects.featured_filter')),
            ])
            ->recordActions([
                EditAction::make(),
                self::deleteAction(),
                Action::make('toggleStatus')
                    ->label(fn (Project $record): string => $record->status === ProjectStatus::Published ? __('admin.projects.unpublish') : __('admin.projects.publish'))
                    ->icon(fn (Project $record) => $record->status === ProjectStatus::Published ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedGlobeAlt)
                    ->color(fn (Project $record): string => $record->status === ProjectStatus::Published ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Project $record, ChangeProjectStatus $change): void {
                        $target = $record->status === ProjectStatus::Published ? ProjectStatus::Draft : ProjectStatus::Published;
                        try {
                            $change->handle($record, $target);
                        } catch (ValidationException) {
                            Notification::make()->danger()->title(__('admin.projects.publish_blocked'))->send();

                            return;
                        }
                        Notification::make()->success()
                            ->title($target === ProjectStatus::Published ? __('admin.projects.published') : __('admin.projects.unpublished'))
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (DeleteBulkAction $action, Collection $records, DeleteProject $delete): void {
                            foreach ($records as $record) {
                                if (! $delete->handle($record)) {
                                    $action->reportBulkProcessingFailure();
                                }
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Deletes the project with its translations, links and image files (App\Actions\Projects\DeleteProject).
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->using(fn (Project $record, DeleteProject $delete): bool => $delete->handle($record));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    /**
     * Title in the interface language, else the other language.
     */
    public static function title(Project $project): ?string
    {
        return Translated::pick($project->translations, 'title');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function content(Project $project, Locale $locale): ?array
    {
        return $project->translations->first(fn ($t) => $t->locale === $locale)?->only(ProjectContent::fields());
    }
}
