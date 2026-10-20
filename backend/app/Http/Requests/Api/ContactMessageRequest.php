<?php

namespace App\Http\Requests\Api;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A message sent from the public contact form. Limits match the database columns and the
 * form on the website. Spam is limited by the "contact" rate limiter, not by hidden trap
 * fields: browser autofill and password managers filled those and real messages were lost.
 */
class ContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'locale' => ['required', 'string', Rule::enum(Locale::class)],
        ];
    }
}
