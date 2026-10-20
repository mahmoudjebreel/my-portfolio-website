<?php

namespace App\Mail;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the site owner that a contact message arrived. The visitor's text is shown escaped
 * (never as HTML or Markdown); "Reply" in the mail program answers the visitor directly.
 */
class NewContactMessageMail extends Mailable
{
    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contactMessage->email, $this->contactMessage->name)],
            subject: __('admin.notification_mail.subject', ['subject' => str($this->contactMessage->subject)->squish()->limit(80)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-contact-message',
            text: 'mail.new-contact-message-text',
            with: [
                'panelUrl' => ContactMessageResource::getUrl('view', ['record' => $this->contactMessage], panel: 'admin'),
            ],
        );
    }
}
