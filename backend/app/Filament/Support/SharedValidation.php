<?php

namespace App\Filament\Support;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates Filament form data with the same rules as the admin API FormRequests
 * (ProjectRequest, CategoryRequest, TechnologyRequest: static rulesFor / afterFor /
 * messagesFor), so the panel and the API can never disagree.
 *
 * Errors are re-keyed to the form state path ("data.…") so Filament shows each one under its
 * field; errors that belong to no field (for example "translations") are also shown as a
 * notification.
 */
final class SharedValidation
{
    /**
     * @param  class-string  $request  a FormRequest class with static rulesFor / afterFor / messagesFor
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $attributes  field key => label shown in messages
     * @param  list<string>  $generalKeys  error keys without a form field
     * @return array<string, mixed> the validated data
     *
     * @throws ValidationException
     */
    public static function validate(string $request, ?Model $record, array $input, array $attributes = [], array $generalKeys = ['translations']): array
    {
        $validator = Validator::make($input, $request::rulesFor($record, $input), $request::messagesFor(), $attributes);
        foreach ($request::afterFor($record, $input) as $hook) {
            $validator->after($hook);
        }

        if (! $validator->fails()) {
            return $validator->validated();
        }

        $messages = [];
        $general = [];
        foreach ($validator->errors()->messages() as $key => $list) {
            $messages["data.{$key}"] = $list;
            if (in_array($key, $generalKeys, true)) {
                $general[] = $list[0];
            }
        }

        Notification::make()
            ->danger()
            ->title(__('admin.validation.failed'))
            ->body($general === [] ? null : implode(' ', $general))
            ->send();

        throw ValidationException::withMessages($messages);
    }

    /**
     * Re-keys a ValidationException thrown by a shared action (keys such as "status") to the
     * form state path, so the message appears under the matching field.
     */
    public static function forForm(ValidationException $exception): ValidationException
    {
        $messages = [];
        foreach ($exception->errors() as $key => $list) {
            $messages[str_starts_with($key, 'data.') ? $key : "data.{$key}"] = $list;
        }

        Notification::make()->danger()->title(__('admin.validation.failed'))->body(collect($exception->errors())->flatten()->first())->send();

        return ValidationException::withMessages($messages);
    }
}
