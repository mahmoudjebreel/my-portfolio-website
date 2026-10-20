{{-- Plain-text part: not HTML, so values are printed as-is. --}}
{!! __('admin.notification_mail.heading') !!}

{!! __('admin.messages.fields.name') !!}: {!! $contactMessage->name !!}
{!! __('admin.messages.fields.email') !!}: {!! $contactMessage->email !!}
{!! __('admin.messages.fields.subject') !!}: {!! $contactMessage->subject !!}

{!! $contactMessage->message !!}

{!! __('admin.notification_mail.open') !!}: {!! $panelUrl !!}
{!! __('admin.notification_mail.reply_hint') !!}
