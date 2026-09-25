<?php

namespace Epesi\Modules\Mail\Filament\Actions;

use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The link to the compose page — Epesi opened Roundcube's compose window for
 * this. A new message (optionally about a record, whose address it starts
 * with), or reply, reply-all and forward of an archived one.
 */
class ComposeAction
{
    public const NEW = 'new';

    public const REPLY = 'reply';

    public const REPLY_ALL = 'replyAll';

    public const FORWARD = 'forward';

    public static function make(?Model $record = null, ?Mail $source = null, string $mode = self::NEW, ?string $name = null): Action
    {
        return Action::make($name ?? 'compose'.Str::studly($mode))
            ->label(static::label($mode))
            ->icon(match ($mode) {
                self::REPLY, self::REPLY_ALL => Heroicon::OutlinedArrowUturnLeft,
                self::FORWARD => Heroicon::OutlinedArrowUturnRight,
                default => Heroicon::OutlinedPaperAirplane,
            })
            ->color($mode === self::NEW ? 'primary' : 'gray')
            ->visible(fn (): bool => static::sendingAccounts() !== [])
            ->url(fn (): string => static::url($record, $source, $mode));
    }

    /**
     * The compose page; $to, when given, replaces the record's own address as
     * the recipient (an address clicked in a list).
     */
    public static function url(?Model $record = null, ?Mail $source = null, string $mode = self::NEW, ?string $to = null): string
    {
        return MailResource::getUrl('compose', array_filter([
            'mode' => $mode === self::NEW ? null : $mode,
            'about' => $record ? $record->getMorphClass().':'.$record->getKey() : null,
            'source' => $source?->getKey(),
            'to' => $to,
        ]));
    }

    /**
     * sendingAccounts() !== [], asked once per request: a list asks it for
     * every e-mail address it links.
     */
    public static function canSend(): bool
    {
        $user = Auth::id();

        return once(fn (): bool => $user !== null && static::sendingAccounts() !== []);
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::REPLY => 'Reply',
            self::REPLY_ALL => 'Reply all',
            self::FORWARD => 'Forward',
            default => 'Send e-mail',
        };
    }

    /**
     * @return array<int, string> the current user's accounts that can send, id => label
     */
    public static function sendingAccounts(): array
    {
        return MailAccount::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('smtp_host')
            ->orderByDesc('is_default')
            ->get()
            ->mapWithKeys(fn (MailAccount $a): array => [$a->getKey() => "{$a->name} <{$a->email}>"])
            ->all();
    }
}
