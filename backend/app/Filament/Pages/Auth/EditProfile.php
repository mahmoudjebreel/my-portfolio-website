<?php

namespace App\Filament\Pages\Auth;

use App\Console\Commands\CreateAdminCommand;
use App\Models\User;
use App\Support\AdminAvatar;
use App\Support\WebpImage;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SensitiveParameter;

/**
 * "My profile" for the signed-in administrator (Filament's profile page, extended):
 *
 * - always the signed-in account only (Filament uses the authenticated user; no id in the URL);
 * - name and email (valid, unique among accounts); no role or permission field is shown, and
 *   only name / email / password are ever written (is_admin is not even mass-assignable);
 * - the current password is required to change the email or the password (Filament);
 * - new password: at least 12 characters (the admin:create policy), confirmed, stored with
 *   Laravel's hashing; the session hash is refreshed by Filament so this session stays signed in
 *   while AuthenticateSession ends the account's other sessions;
 * - account picture: JPG / PNG / WebP up to 5 MB, previewed before saving, re-encoded to WebP
 *   at a fixed server-made path (App\Support\AdminAvatar); "remove" deletes that file only.
 * - email changes are not verified by email (no mail service is configured); the current
 *   password is the confirmation.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.profile.picture'))
                ->aside()
                ->description(__('admin.profile.picture_help'))
                ->schema([
                    Image::make(fn (): string => AdminAvatar::url($this->getUser()) ?? '', __('admin.profile.current_picture'))
                        ->imageSize(96)
                        ->visible(fn (): bool => AdminAvatar::exists($this->getUser())),
                    Text::make(__('admin.profile.no_picture'))
                        ->color('gray')
                        ->visible(fn (): bool => ! AdminAvatar::exists($this->getUser())),
                    FileUpload::make('avatar')
                        ->label(__('admin.profile.new_picture'))
                        ->image()
                        ->imagePreviewHeight('160')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(intdiv(WebpImage::MAX_BYTES, 1024))
                        ->storeFiles(false),
                    Toggle::make('remove_avatar')
                        ->label(__('admin.profile.remove_picture'))
                        ->visible(fn (): bool => AdminAvatar::exists($this->getUser())),
                ]),
            Section::make(__('admin.profile.account'))
                ->aside()
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                ]),
            Section::make(__('admin.profile.password'))
                ->aside()
                ->description(__('admin.profile.password_help', ['min' => CreateAdminCommand::MIN_PASSWORD_LENGTH]))
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $email */
        $email = parent::getEmailFormComponent();

        return $email->maxLength(254)->rule('email:rfc')->extraInputAttributes(['dir' => 'ltr']);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $password */
        $password = parent::getPasswordFormComponent();

        return $password->rule(Password::min(CreateAdminCommand::MIN_PASSWORD_LENGTH))->maxLength(1000);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        $avatar = $data['avatar'] ?? null;
        $remove = (bool) ($data['remove_avatar'] ?? false);

        // Only these columns are ever written from this page.
        $record = parent::handleRecordUpdate($record, Arr::only($data, ['name', 'email', 'password']));

        try {
            if ($avatar instanceof TemporaryUploadedFile) {
                AdminAvatar::store($record, (string) $avatar->get());
            } elseif ($remove) {
                AdminAvatar::remove($record);
            }
        } catch (ValidationException $exception) {
            // The database changes are rolled back by the page; show the message under the field.
            throw ValidationException::withMessages(['data.avatar' => $exception->errors()['avatar'] ?? [__('admin.validation.failed')]]);
        }

        return $record;
    }

    /**
     * Reload the page after saving so the new picture or name shows in the header at once.
     */
    protected function getRedirectUrl(): ?string
    {
        return static::getUrl();
    }
}
