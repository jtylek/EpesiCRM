<?php

namespace Epesi\Modules\RecordBrowser\Models;

use Closure;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * An account on another service, as a collection item: a record's website,
 * its LinkedIn profile, a Telegram or Teams account — whatever identifies it
 * there. Its kind is the service (`Online_Account_Kinds`: Website, LinkedIn,
 * Telegram, Microsoft Teams, Facebook, X, Instagram, GitHub, Other) and is required,
 * since the service decides what the handle links to. Any recordset takes
 * them with `Field::collection('online_accounts', OnlineAccount::class)`.
 *
 * The handle is a username (`@ann` or `ann`), an address (Teams), or a full
 * URL pasted from the browser, which links to itself whatever the service.
 * A number on WhatsApp or Signal is a phone number with its messengers
 * ticked (PhoneNumber), not an online account.
 *
 * The form checks the handle's *shape* against the chosen service
 * (handleRule()): Website and a pasted profile URL both need something that
 * looks like a domain, Teams needs an e-mail address, and everything else
 * needs a plain handle. It can't check whether the account is real — "jdfksdfj"
 * is exactly as valid-looking a handle as a real one, and only the service
 * itself could tell them apart.
 *
 * @property ?string $value
 */
class OnlineAccount extends CollectionItem
{
    protected $table = 'epesi_recordbrowser_online_accounts';

    /**
     * Where a handle links on each service the list starts with, %s being
     * the handle without a leading @. A website is its own address, and a
     * service an administrator adds has no pattern: CommonData holds only a
     * key and a label.
     */
    protected const PROFILE_LINKS = [
        'linkedin' => 'https://www.linkedin.com/in/%s',
        'telegram' => 'https://t.me/%s',
        'teams' => 'https://teams.microsoft.com/l/chat/0/0?users=%s',
        'facebook' => 'https://www.facebook.com/%s',
        'x' => 'https://x.com/%s',
        'instagram' => 'https://www.instagram.com/%s',
        'github' => 'https://github.com/%s',
    ];

    public static function fields(): array
    {
        return [
            Field::text('value')->label('Handle')->required()->inTable()
                ->formUsing(fn (TextInput $input): TextInput => $input
                    ->rule(fn (Get $get): Closure => static::handleRule((string) $get('kind')))),
        ];
    }

    public static function kinds(): string
    {
        return 'Online_Account_Kinds';
    }

    public static function kindFieldLabel(): string
    {
        return 'Service';
    }

    public static function kindRequired(): bool
    {
        return true;
    }

    public static function addActionLabel(): string
    {
        return __('Add online account');
    }

    /** The handle as typed. */
    public function summary(): string
    {
        return (string) $this->value;
    }

    /**
     * The handle's page on its service. A URL, with or without its scheme,
     * is its own page; a website is its address; a service with no pattern
     * links nowhere.
     */
    public function url(): ?string
    {
        $handle = trim((string) $this->value);

        if ($handle === '') {
            return null;
        }

        if ($this->kind === 'website' || static::isUrlLike($handle)) {
            return Field::webAddressUrl($handle);
        }

        $pattern = static::PROFILE_LINKS[$this->kind] ?? null;

        return $pattern === null ? null : sprintf($pattern, rawurlencode(ltrim($handle, '@')));
    }

    /**
     * Whether $value already names its own page — url() links straight to
     * it, whatever the kind, the same way a pasted URL does for a `Related`
     * field's search.
     */
    protected static function isUrlLike(string $value): bool
    {
        return (bool) preg_match('#^https?://#i', $value) || str_contains($value, '/');
    }

    /**
     * Whether $value's *shape* fits $kind — not whether the account exists,
     * which only the service itself could tell. Website, and any kind given
     * a pasted URL (isUrlLike()), need something that looks like a domain
     * (looksLikeADomain()); Teams needs an e-mail address, since that's what
     * it holds; every other kind needs a plain handle: letters, digits,
     * ".", "_" and "-", with an optional leading "@" — which "jdfksdfj"
     * satisfies exactly as well as a real one.
     */
    protected static function handleRule(string $kind): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($kind): void {
            $value = trim((string) $value);

            if ($value === '') {
                return;
            }

            if ($kind === 'teams') {
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $fail(__('Doesn\'t look like an e-mail address.'));
                }

                return;
            }

            if ($kind === 'website' || static::isUrlLike($value)) {
                if (! static::looksLikeADomain($value)) {
                    $fail(__('Doesn\'t look like a web address.'));
                }

                return;
            }

            if (! preg_match('/^@?[A-Za-z0-9_.-]{1,64}$/', $value)) {
                $fail(__('Doesn\'t look like a handle: only letters, digits, ".", "_" and "-".'));
            }
        };
    }

    /** A host with at least one dot, with or without a scheme, a port and a path. */
    protected static function looksLikeADomain(string $value): bool
    {
        return (bool) preg_match('/^(https?:\/\/)?[^\s:\/]+\.[^\s:\/]+(:\d+)?(\/\S*)?$/i', $value);
    }
}
