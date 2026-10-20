<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Account pictures of administrators, without any database column: one file per account at
 * a fixed, server-made path on the private "local" disk (avatars/{user id}.webp). Uploads are
 * checked from their content and re-encoded to WebP (WebpImage), so the stored file is always
 * a plain image. Removing deletes only that account's own file. Works without `storage:link`:
 * the image is served by an authenticated panel route.
 */
final class AdminAvatar
{
    public const DISK = 'local';

    /** Stored avatars are scaled down to this longest side. */
    public const MAX_SIDE = 512;

    public static function path(User $user): string
    {
        return 'avatars/'.(int) $user->getKey().'.webp';
    }

    public static function exists(User $user): bool
    {
        return $user->exists && Storage::disk(self::DISK)->exists(self::path($user));
    }

    /**
     * URL of the account's own picture for the panel, with a version so a new upload shows at
     * once; null when no picture is set (the initials avatar is used instead).
     */
    public static function url(User $user): ?string
    {
        if (! self::exists($user)) {
            return null;
        }

        return route('filament.admin.account-avatar', ['v' => Storage::disk(self::DISK)->lastModified(self::path($user))]);
    }

    /**
     * @throws ValidationException (key "avatar") when the file is not an acceptable image
     */
    public static function store(User $user, string $bytes): void
    {
        try {
            $encoded = WebpImage::encode($bytes, self::MAX_SIDE);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['avatar' => __($exception->getMessage())]);
        }

        if (! Storage::disk(self::DISK)->put(self::path($user), $encoded['bytes'])) {
            throw new RuntimeException('The account picture could not be stored.');
        }
    }

    /**
     * Deletes this account's own picture file only (nothing else is ever touched).
     */
    public static function remove(User $user): void
    {
        if (self::exists($user)) {
            Storage::disk(self::DISK)->delete(self::path($user));
        }
    }

    public static function response(User $user): BinaryFileResponse
    {
        abort_unless(self::exists($user), 404);

        return response()->file(Storage::disk(self::DISK)->path(self::path($user)), [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
