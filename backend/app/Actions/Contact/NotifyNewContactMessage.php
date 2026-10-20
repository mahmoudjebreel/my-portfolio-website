<?php

namespace App\Actions\Contact;

use App\Mail\NewContactMessageMail;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails a new contact message to the "Notification email" site setting. Without that
 * setting nothing is sent. A mail failure never loses the message: it stays in the inbox,
 * the error is logged and `notified_at` stays empty.
 */
final class NotifyNewContactMessage
{
    public function handle(ContactMessage $message): bool
    {
        $recipient = trim((string) SiteSetting::query()->where('key', 'notification_email')->value('value'));
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        try {
            Mail::to($recipient)->locale('ar')->send(new NewContactMessageMail($message));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $message->forceFill(['notified_at' => now()])->save();

        return true;
    }
}
