<?php

namespace App\Http\Controllers\Api;

use App\Actions\Contact\NotifyNewContactMessage;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    /**
     * Stores a message from the public contact form as unread, for the administration inbox.
     * The response never echoes the submitted data. The email notification is sent after the
     * response, so a slow or failing mail server never delays or breaks the form.
     */
    public function store(ContactMessageRequest $request, NotifyNewContactMessage $notify): JsonResponse
    {
        $message = ContactMessage::create([
            ...$request->safe()->only(['name', 'email', 'subject', 'message', 'locale']),
            'status' => MessageStatus::Unread,
        ]);

        defer(fn () => $notify->handle($message));

        return response()->json(['data' => ['received' => true]], 201);
    }
}
