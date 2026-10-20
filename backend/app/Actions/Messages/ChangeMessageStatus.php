<?php

namespace App\Actions\Messages;

use App\Enums\MessageStatus;
use App\Models\ContactMessage;
use DomainException;

/**
 * Marks a contact message read or unread (admin API and Filament). Read keeps the first read
 * time; unread clears it. Archived messages, and archiving itself, are not handled here.
 */
final class ChangeMessageStatus
{
    /**
     * @throws DomainException for an archived message or an unsupported target status
     */
    public function handle(ContactMessage $message, MessageStatus $status): ContactMessage
    {
        if ($message->status === MessageStatus::Archived) {
            throw new DomainException('Archived messages cannot be changed here.');
        }
        if ($status === MessageStatus::Archived) {
            throw new DomainException('Messages cannot be archived here.');
        }

        $message->status = $status;
        $message->read_at = $status === MessageStatus::Read ? ($message->read_at ?? now()) : null;
        $message->save();

        return $message;
    }

    public static function unreadCount(): int
    {
        return ContactMessage::query()->where('status', MessageStatus::Unread)->count();
    }
}
