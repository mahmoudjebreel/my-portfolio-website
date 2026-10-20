<?php

namespace App\Http\Requests\Api;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional `locale` query parameter (en|ar, default en).
 */
class LocaleRequest extends FormRequest
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
            'locale' => ['sometimes', 'string', Rule::enum(Locale::class)],
        ];
    }

    public function contentLocale(): Locale
    {
        return Locale::from($this->validated('locale', Locale::En->value));
    }
}
