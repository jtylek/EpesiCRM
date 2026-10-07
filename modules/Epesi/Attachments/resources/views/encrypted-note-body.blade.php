@php
    $record = $getRecord();
    $decrypted = method_exists($getLivewire(), 'getDecryptedLegacyNoteHtml') ? $getLivewire()->getDecryptedLegacyNoteHtml() : null;
@endphp
@if ($decrypted !== null)
    <div class="fi-prose" style="overflow-wrap: anywhere">{!! str($decrypted)->sanitizeHtml() !!}</div>
@elseif ($record->legacy_encrypted)
    <span>{{ __('This note is password protected. Enter its password with the Decrypt note action.') }}</span>
@endif
