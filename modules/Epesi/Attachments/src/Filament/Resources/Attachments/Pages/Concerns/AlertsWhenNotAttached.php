<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A save refused for what the note is "Attached to" (no record, or a row left
 * half filled) is told in a notification too: that field sits below the note
 * itself, out of sight on a long one, and a line of red text in it is easy to
 * miss.
 */
trait AlertsWhenNotAttached
{
    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        $message = collect($exception->errors())
            ->filter(fn (array $messages, string $key): bool => $key === 'data.attach_to' || Str::startsWith($key, 'data.attach_to.'))
            ->flatten()
            ->first();

        if ($message !== null) {
            Notification::make()
                ->danger()
                ->title(__('The note was not saved'))
                ->body($message)
                ->send();
        }
    }
}
