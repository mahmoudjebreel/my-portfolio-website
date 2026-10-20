<?php

namespace App\Filament\Pages;

use App\Actions\Settings\SaveSiteSettings;
use App\Enums\Locale;
use App\Filament\Support\SharedValidation;
use App\Http\Requests\Admin\SiteSettingsRequest;
use App\Support\SiteSettingsForm;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Site settings in the Filament panel: only the keys of SiteSettingsForm (from
 * SiteSettingsRegistry), validated with the SiteSettingsRequest rules and saved by
 * App\Actions\Settings\SaveSiteSettings. Opening the page never changes anything; values are
 * saved only with the Save button. Image settings are shown read-only, without paths.
 */
class SiteSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Groups of translated texts, in display order. */
    private const TEXT_GROUPS = [
        'identity' => ['display_name', 'professional_title'],
        'hero' => ['hero_eyebrow', 'hero_headline', 'hero_description'],
        'about' => ['about_text', 'experience_text'],
        'footer' => ['footer_text'],
        'seo' => ['seo_default_title', 'seo_default_description'],
    ];

    private const MULTILINE = ['hero_description', 'about_text', 'experience_text', 'seo_default_description'];

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.site');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.settings.title');
    }

    public function mount(): void
    {
        $this->fillFromDatabase();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $sections = [Text::make(__('admin.settings.intro'))->color('gray')];

        foreach (self::TEXT_GROUPS as $group => $keys) {
            $sections[] = Section::make(__("admin.settings.groups.{$group}"))
                ->collapsible()
                ->schema(array_map(fn (string $key) => Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                    fn (Locale $locale) => $this->textField($key, $locale),
                    Locale::cases(),
                )), $keys));
        }

        $sections[] = Grid::make(['default' => 1, 'lg' => 2])->schema([
            Section::make(__('admin.settings.groups.contact'))->schema([
                $this->valueField('contact_email', 'email'),
                $this->valueField('github_url', 'url'),
                $this->valueField('linkedin_url', 'url'),
            ]),
            Grid::make(1)->schema([
                Section::make(__('admin.settings.groups.private'))->schema([
                    $this->valueField('notification_email', 'email')->helperText(__('admin.settings.notification_note')),
                ]),
                Section::make(__('admin.settings.groups.images'))->schema([
                    ...array_map(fn (string $key) => Text::make(fn (): string => __("admin.settings.fields.{$key}").': '.$this->imageState($key)), SiteSettingsForm::imageKeys()),
                    Text::make(__('admin.settings.images_note'))->color('gray'),
                ]),
            ]),
        ]);

        $sections[] = Text::make(__('admin.settings.empty_note'))->color('gray');

        return $schema->columns(1)->components($sections);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('admin.settings.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ])->sticky()->key('form-actions'),
                ]),
        ]);
    }

    public function save(SaveSiteSettings $save): void
    {
        $state = $this->form->getState();
        $text = fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $input = ['texts' => [], 'values' => []];
        $attributes = [];
        foreach (SiteSettingsForm::textKeys() as $key) {
            foreach (Locale::cases() as $locale) {
                $input['texts'][$key][$locale->value] = $text($state['texts'][$key][$locale->value] ?? null);
                $attributes["texts.{$key}.{$locale->value}"] = __("admin.settings.fields.{$key}");
            }
        }
        foreach (array_keys(SiteSettingsForm::valueKeys()) as $key) {
            $input['values'][$key] = $text($state['values'][$key] ?? null);
            $attributes["values.{$key}"] = __("admin.settings.fields.{$key}");
        }

        $validated = SharedValidation::validate(SiteSettingsRequest::class, null, $input, $attributes, ['texts', 'values']);
        $save->handle($validated['texts'], $validated['values']);

        $this->fillFromDatabase();
        Notification::make()->success()->title(__('admin.settings.saved'))->send();
    }

    private function fillFromDatabase(): void
    {
        $snapshot = SaveSiteSettings::snapshot();
        $this->form->fill(['texts' => $snapshot['texts'], 'values' => $snapshot['values']]);
    }

    private function textField(string $key, Locale $locale): TextInput|Textarea
    {
        $field = in_array($key, self::MULTILINE, true)
            ? Textarea::make("texts.{$key}.{$locale->value}")->rows(3)
            : TextInput::make("texts.{$key}.{$locale->value}");

        return $field
            ->label(__("admin.settings.fields.{$key}").' — '.__('admin.settings.'.($locale === Locale::En ? 'english' : 'arabic')))
            ->maxLength(SiteSettingsForm::TEXT_LIMITS[$key])
            ->extraInputAttributes(['dir' => $locale === Locale::Ar ? 'rtl' : 'ltr', 'lang' => $locale->value]);
    }

    private function valueField(string $key, string $type): TextInput
    {
        $field = TextInput::make("values.{$key}")
            ->label(__("admin.settings.fields.{$key}"))
            ->extraInputAttributes(['dir' => 'ltr']);

        return $type === 'email'
            ? $field->email()->maxLength(SiteSettingsForm::EMAIL_MAX)
            : $field->url()->maxLength(SiteSettingsForm::URL_MAX)->placeholder('https://');
    }

    private function imageState(string $key): string
    {
        $image = SaveSiteSettings::snapshot()['images'][$key];

        return ! $image['set'] ? __('admin.settings.image_not_set') : ($image['url'] ? __('admin.settings.image_set') : __('admin.settings.image_missing'));
    }
}
