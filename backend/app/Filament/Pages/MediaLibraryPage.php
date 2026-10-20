<?php

namespace App\Filament\Pages;

use App\Actions\Media\AddGalleryImage;
use App\Actions\Media\DeleteMedia;
use App\Enums\Locale;
use App\Models\Project;
use App\Support\MediaLibrary;
use App\Support\WebpImage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Media library in the Filament panel: project covers and gallery images
 * (App\Support\MediaLibrary), with an upload that adds a gallery image to a project
 * (App\Actions\Media\AddGalleryImage: real image check, WebP re-encoding, server-made name).
 * Previews come from an authenticated panel route, so `storage:link` is not needed.
 * Gallery images can be deleted and covers removed (App\Actions\Media\DeleteMedia); a file is
 * deleted from the disk only when nothing else uses it.
 */
class MediaLibraryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'media';

    public static function getNavigationLabel(): string
    {
        return __('admin.media.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.media.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(__('admin.media.intro'))->color('gray'),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $project = filled($filters['project']['value'] ?? null) ? (int) $filters['project']['value'] : null;
                $kind = in_array($filters['kind']['value'] ?? null, ['cover', 'gallery'], true) ? $filters['kind']['value'] : null;
                $items = MediaLibrary::items($project, $kind, (string) $search);

                $pageItems = $items->forPage($page, $recordsPerPage)
                    ->map(fn (array $item) => MediaLibrary::describe($item, self::previewUrl(...)))
                    ->keyBy('key');

                return new LengthAwarePaginator($pageItems, $items->count(), $recordsPerPage, $page);
            })
            ->columns([
                ImageColumn::make('preview_url')
                    ->label(__('admin.media.preview'))
                    ->imageHeight(56)
                    ->extraImgAttributes(['loading' => 'lazy', 'alt' => ''])
                    ->placeholder(__('admin.media.unavailable')),
                TextColumn::make('file_name')
                    ->label(__('admin.media.file_name'))
                    ->description(fn (array $record): string => __('admin.media.kinds.'.$record['kind']))
                    ->fontFamily('mono')
                    ->limit(36)
                    ->placeholder('—'),
                TextColumn::make('project_title')
                    ->label(__('admin.media.project'))
                    ->state(fn (array $record): string => self::projectTitle($record['project']['title'])),
                TextColumn::make('size')
                    ->label(__('admin.media.size'))
                    ->formatStateUsing(fn ($state): string => self::bytes($state))
                    ->placeholder('—'),
                TextColumn::make('dimensions')
                    ->label(__('admin.media.dimensions'))
                    ->state(fn (array $record): ?string => $record['width'] && $record['height'] ? "{$record['width']} × {$record['height']}" : null)
                    ->placeholder('—'),
                TextColumn::make('updated_at')->label(__('admin.media.updated'))->since()->dateTimeTooltip(),
            ])
            ->searchable()
            ->filters([
                SelectFilter::make('project')->label(__('admin.media.project'))->options(fn (): array => self::projectOptions()),
                SelectFilter::make('kind')
                    ->label(__('admin.media.kind'))
                    ->options(['cover' => __('admin.media.kinds.cover'), 'gallery' => __('admin.media.kinds.gallery')]),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('admin.media.details'))
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (array $record): string => $record['file_name'] ?? __('admin.media.unavailable'))
                    ->modalContent(fn (array $record) => view('filament.admin.media-details', [
                        'item' => $record,
                        'projectTitle' => self::projectTitle($record['project']['title']),
                        'size' => self::bytes($record['size']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-actions::view.single.modal.actions.close.label')),
                Action::make('delete')
                    ->label(fn (array $record): string => $record['kind'] === 'cover' ? __('admin.media.remove_cover') : __('filament-actions::delete.single.label'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => $record['kind'] === 'cover' ? __('admin.media.remove_cover') : __('admin.media.delete_image'))
                    ->modalDescription(fn (array $record): string => $record['kind'] === 'cover' ? __('admin.media.remove_cover_help') : __('admin.media.delete_image_help'))
                    ->action(function (array $record, DeleteMedia $delete): void {
                        $done = $delete->handle($record['kind'], (int) $record['id']);
                        Notification::make()
                            ->{$done ? 'success' : 'danger'}()
                            ->title($done ? __('admin.media.deleted') : __('admin.media.delete_failed'))
                            ->send();
                        $this->resetTable();
                    }),
            ])
            ->toolbarActions([])
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(12);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(__('admin.media.upload'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading(__('admin.media.upload_heading'))
                ->modalDescription(__('admin.media.upload_help'))
                ->schema([
                    Select::make('project_id')
                        ->label(__('admin.media.project'))
                        ->options(fn (): array => self::projectOptions())
                        ->searchable()
                        ->required()
                        ->rules(['integer', 'exists:projects,id']),
                    FileUpload::make('file')
                        ->label(__('admin.media.file'))
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(intdiv(WebpImage::MAX_BYTES, 1024))
                        ->storeFiles(false)
                        ->required(),
                    ...array_map(fn (Locale $locale) => TextInput::make("alt.{$locale->value}")
                        ->label(__('admin.media.alt', ['language' => __('admin.common.'.($locale === Locale::En ? 'english' : 'arabic'))]))
                        ->maxLength(200)
                        ->extraInputAttributes(['dir' => $locale === Locale::Ar ? 'rtl' : 'ltr', 'lang' => $locale->value]), Locale::cases()),
                ])
                ->action(function (array $data, Action $action, AddGalleryImage $add): void {
                    $file = $data['file'];
                    if (! $file instanceof TemporaryUploadedFile) {
                        Notification::make()->danger()->title(__('admin.validation.failed'))->send();
                        $action->halt();
                    }

                    try {
                        $add->handle(Project::query()->findOrFail($data['project_id']), (string) $file->get(), (array) ($data['alt'] ?? []));
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                        $action->halt();
                    }

                    Notification::make()->success()->title(__('admin.media.uploaded'))->send();
                    $this->resetTable();
                }),
        ];
    }

    public static function previewUrl(string $kind, int $id): string
    {
        return route('filament.admin.media-preview', ['kind' => $kind, 'id' => $id]);
    }

    /**
     * @return array<int, string>
     */
    private static function projectOptions(): array
    {
        return MediaLibrary::projectOptions()
            ->mapWithKeys(fn (array $project) => [$project['id'] => self::projectTitle($project['title'])])
            ->all();
    }

    /**
     * @param  array<string, string|null>  $titles
     */
    private static function projectTitle(array $titles): string
    {
        $current = App::getLocale() === 'ar' ? 'ar' : 'en';

        return $titles[$current] ?? $titles[$current === 'ar' ? 'en' : 'ar'] ?? __('admin.media.untitled');
    }

    private static function bytes(mixed $size): string
    {
        if (! is_int($size)) {
            return '—';
        }

        return $size < 1024 * 1024
            ? __('admin.media.kb', ['size' => max(1, (int) round($size / 1024))])
            : __('admin.media.mb', ['size' => number_format($size / 1024 / 1024, 1)]);
    }
}
