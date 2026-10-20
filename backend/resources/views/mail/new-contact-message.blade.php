<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.notification_mail.heading') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;">
        <h1 style="margin:0 0 16px;font-size:20px;">{{ __('admin.notification_mail.heading') }}</h1>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <tr>
                <td style="padding:6px 0;color:#64748b;width:120px;">{{ __('admin.messages.fields.name') }}</td>
                <td style="padding:6px 0;" dir="auto">{{ $contactMessage->name }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#64748b;">{{ __('admin.messages.fields.email') }}</td>
                <td style="padding:6px 0;" dir="ltr">{{ $contactMessage->email }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#64748b;">{{ __('admin.messages.fields.subject') }}</td>
                <td style="padding:6px 0;" dir="auto">{{ $contactMessage->subject }}</td>
            </tr>
        </table>
        <div dir="auto" style="margin:16px 0;padding:16px;background:#f8fafc;border-radius:8px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;line-height:1.6;">{{ $contactMessage->message }}</div>
        <p style="margin:0 0 16px;">
            <a href="{{ $panelUrl }}" style="display:inline-block;background:#7c3aed;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:999px;">{{ __('admin.notification_mail.open') }}</a>
        </p>
        <p style="margin:0;color:#64748b;font-size:12px;">{{ __('admin.notification_mail.reply_hint') }}</p>
    </div>
</body>
</html>
