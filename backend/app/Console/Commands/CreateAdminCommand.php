<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the administrator account, run by its owner:
 *
 *     php artisan admin:create
 *
 * - Interactive only: the password is typed twice with hidden input. It is never
 *   accepted as an option or argument (shell history), printed, or logged.
 * - Never changes an existing account: if the email is taken, nothing is written.
 * - Password: at least 12 characters (no external breach lookup).
 */
class CreateAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:create';

    /**
     * @var string
     */
    protected $description = 'Create an administrator account (interactive; never modifies existing users)';

    public const MIN_PASSWORD_LENGTH = 12;

    /** bcrypt only uses the first 72 bytes: longer passwords are rejected, not silently cut. */
    public const MAX_PASSWORD_BYTES = 72;

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('This command is interactive only: run it without --no-interaction and type the password when asked.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Name'));
        $email = Str::lower(trim((string) $this->ask('Email address')));

        $details = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:254'],
            ],
        );

        if ($details->fails()) {
            return $this->refuse($details->errors()->all());
        }

        if (User::query()->where('email', $email)->exists()) {
            return $this->refuse(['A user with this email address already exists. This command never changes existing accounts.']);
        }

        $password = (string) $this->secret('Password (at least '.self::MIN_PASSWORD_LENGTH.' characters, input hidden)');
        $confirmation = (string) $this->secret('Confirm password');

        $passwordCheck = Validator::make(
            ['password' => $password],
            ['password' => [
                'required',
                'string',
                Password::min(self::MIN_PASSWORD_LENGTH),
                function (string $attribute, string $value, \Closure $fail) {
                    if (strlen($value) > self::MAX_PASSWORD_BYTES) {
                        $fail('The password may not be longer than '.self::MAX_PASSWORD_BYTES.' bytes.');
                    }
                },
            ]],
        );

        if ($passwordCheck->fails()) {
            return $this->refuse($passwordCheck->errors()->all());
        }

        if (! hash_equals($password, $confirmation)) {
            return $this->refuse(['The passwords do not match.']);
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        // Set explicitly: is_admin is (and stays) excluded from mass assignment.
        $user->is_admin = true;
        $user->email_verified_at = now();

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            return $this->refuse(['A user with this email address already exists. This command never changes existing accounts.']);
        }

        $this->info("Administrator account created for {$email}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $messages
     */
    private function refuse(array $messages): int
    {
        foreach ($messages as $message) {
            $this->error($message);
        }
        $this->error('No account was created.');

        return self::FAILURE;
    }
}
