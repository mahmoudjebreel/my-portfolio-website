<?php

namespace App\Support;

/**
 * mailto: link for replying to a contact message. It only opens the administrator's mail
 * program (nothing is sent). Each part of the address is percent-encoded, so characters that
 * are valid in email addresses such as ? & # cannot add recipients or headers.
 */
final class ReplyLink
{
    /**
     * @return string|null null when the stored address is not a valid email
     */
    public static function for(?string $email, ?string $subject): ?string
    {
        $email = trim((string) $email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $at = strrpos($email, '@');
        $address = rawurlencode(substr($email, 0, $at)).'@'.rawurlencode(substr($email, $at + 1));
        $subject = trim((string) $subject);

        return 'mailto:'.$address.'?subject='.rawurlencode($subject === '' ? 'Re:' : "Re: {$subject}");
    }
}
