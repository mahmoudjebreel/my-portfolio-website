<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Default avatar: the account initials drawn as an inline SVG (data: URI). Replaces
 * Filament's default provider, which loads the image from ui-avatars.com and so sends the
 * administrator's initials to an outside service on every page.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $segment), 0, 1))
            ->filter()
            ->take(2)
            ->join('');

        $text = htmlspecialchars(mb_strtoupper($initials !== '' ? $initials : '?'), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#5b21b6"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="system-ui, sans-serif" font-size="26" font-weight="600">'.$text.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
