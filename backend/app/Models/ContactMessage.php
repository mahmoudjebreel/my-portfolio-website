<?php

namespace App\Models;

use App\Enums\Locale;
use App\Enums\MessageStatus;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'subject', 'message', 'locale', 'status', 'read_at', 'notified_at'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'status' => MessageStatus::class,
            'read_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
