<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Notifications\VerifyContactEmail;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * A customer's e-mail addresses in the portal (AI-shared/Customer-portal.md),
 * which a customer can't simply edit like the rest of their contact, because
 * one of them is the address they sign in with:
 *
 * - the login address (users.email) is the Primary one and always counts as
 *   Verified — the administrator sent the link to it, and a password reset
 *   confirms it (verifyLogin()). It can't be removed;
 * - an address added here is unverified until its owner opens the link e-mailed
 *   to it (sendVerification(), verify());
 * - only a verified address can become the Primary one, which changes the
 *   login e-mail (makePrimary()).
 *
 * The addresses are the contact's own `emails` collection (EmailAddress), so
 * staff see them in Contacts as always; verified_at is the only addition.
 */
class PortalEmails
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    /**
     * The contact's addresses, the Primary one first.
     *
     * @return Collection<int, array{id: int, value: string, primary: bool, verified: bool}>
     */
    public static function rows(User $user, Contact $contact): Collection
    {
        self::ensurePrimary($user, $contact);

        return $contact->collection('emails')->orderBy('position')->get()
            ->map(fn (EmailAddress $item): array => [
                'id' => (int) $item->getKey(),
                'value' => (string) $item->value,
                'primary' => self::isLogin($user, $item->value),
                'verified' => self::isLogin($user, $item->value) || $item->verified_at !== null,
            ])
            ->sortByDesc('primary')
            ->values();
    }

    /** The login address is one of the contact's, marked verified: put it there if it isn't. */
    public static function ensurePrimary(User $user, Contact $contact): void
    {
        $items = $contact->collection('emails')->orderBy('position')->get();
        $login = $items->first(fn (EmailAddress $item): bool => self::isLogin($user, $item->value));

        if ($login === null) {
            $contact->syncCollection('emails', [
                ['value' => $user->email],
                ...$items->map(fn (EmailAddress $item): array => self::asData($item))->all(),
            ]);
            $login = $contact->collection('emails')->where('value', mb_strtolower($user->email))->first();
        }

        if ($login !== null && $login->verified_at === null) {
            $login->forceFill(['verified_at' => now()])->save();
        }
    }

    /** A password reset (the link went to the login address) proves the mailbox is theirs. */
    public static function verifyLogin(User $user): void
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($contact = $user->contact) {
            self::ensurePrimary($user, $contact);
        }
    }

    /** The reason an address can't be added, or null. */
    public static function whyNotAdd(User $user, string $address): ?string
    {
        $address = mb_strtolower(trim($address));

        $taken = EmailAddress::query()->whereRaw('LOWER(value) = ?', [$address])->exists()
            || User::query()->whereRaw('LOWER(email) = ?', [$address])->whereKeyNot($user->getKey())->exists();

        return $taken ? __('This address is already used by another record.') : null;
    }

    /** Adds an unverified address and e-mails it the link. Returns SENT or FAILED (added either way). */
    public static function add(User $user, Contact $contact, string $address): string
    {
        $contact->syncCollection('emails', [
            ...$contact->collection('emails')->orderBy('position')->get()
                ->map(fn (EmailAddress $item): array => self::asData($item))->all(),
            ['value' => $address],
        ]);

        $item = $contact->collection('emails')->where('value', mb_strtolower(trim($address)))->first();

        return self::sendVerification($item);
    }

    public static function sendVerification(EmailAddress $item): string
    {
        $url = URL::temporarySignedRoute('portal.verify-email', now()->addDay(), [
            'email' => $item->getKey(),
            'hash' => sha1((string) $item->value),
        ]);

        try {
            Notification::route('mail', $item->value)->notify(new VerifyContactEmail($url));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return self::FAILED;
        }

        return self::SENT;
    }

    /** What the e-mailed link does. False when it doesn't match an address of a contact with a login. */
    public static function verify(int $id, string $hash): bool
    {
        $item = EmailAddress::query()->find($id);

        if ($item === null || ! hash_equals(sha1((string) $item->value), $hash)) {
            return false;
        }

        $owner = $item->owner;

        if (! $owner instanceof Contact || $owner->user_id === null) {
            return false;
        }

        $item->forceFill(['verified_at' => $item->verified_at ?? now()])->save();

        return true;
    }

    /** Removes an address — never the Primary one. Returns whether it did. */
    public static function remove(User $user, Contact $contact, int $id): bool
    {
        $items = $contact->collection('emails')->orderBy('position')->get();
        $item = $items->firstWhere('id', $id);

        if ($item === null || self::isLogin($user, $item->value)) {
            return false;
        }

        $contact->syncCollection('emails', $items->reject(fn (EmailAddress $i): bool => $i->is($item))
            ->map(fn (EmailAddress $i): array => self::asData($i))->all());

        return true;
    }

    /**
     * Makes a verified address the login e-mail. Null when done, else the reason it wasn't.
     */
    public static function makePrimary(User $user, Contact $contact, int $id): ?string
    {
        $items = $contact->collection('emails')->orderBy('position')->get();
        $item = $items->firstWhere('id', $id);

        if ($item === null) {
            return __('This address is not on your contact.');
        }

        if ($item->verified_at === null) {
            return __('Verify this address first: open the link e-mailed to it.');
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($item->value)])->whereKeyNot($user->getKey())->exists()) {
            return __('This address is already used by another login.');
        }

        DB::transaction(function () use ($user, $contact, $items, $item): void {
            $user->forceFill(['email' => $item->value, 'email_verified_at' => now()])->save();

            $contact->syncCollection('emails', $items
                ->sortBy(fn (EmailAddress $i): int => $i->is($item) ? 0 : 1)
                ->map(fn (EmailAddress $i): array => self::asData($i))->all());
        });

        return null;
    }

    protected static function isLogin(User $user, ?string $address): bool
    {
        return mb_strtolower((string) $address) === mb_strtolower((string) $user->email);
    }

    /** @return array{id: int, kind: ?string, value: string} */
    protected static function asData(EmailAddress $item): array
    {
        return ['id' => (int) $item->getKey(), 'kind' => $item->kind, 'value' => (string) $item->value];
    }
}
