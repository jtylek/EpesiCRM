<?php

namespace Epesi\Modules\Mail\Services;

use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Epesi\Modules\RecordBrowser\Models\OnlineAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Who on a message the CRM doesn't know yet, for the Archive button's
 * "Sender not in Contacts" dialog (AI-shared/features/mail/archiving.md,
 * Unknown sender).
 *
 * Only the other party counts: a received message's sender, or the
 * recipients (To) of one sent from your own address. An address is unknown
 * when no live contact or company holds it and it is no mail account's.
 * Your own contact and company never make a message known, which is what
 * hid Epesi's "contact not found" warning in a shared mailbox.
 */
class UnknownSenders
{
    /** At most this many addresses in one dialog. */
    public const MAX = 20;

    /** Domains anyone can have an address at: they say nothing about a company. */
    public const FREE_MAIL = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'ymail.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'gmx.com',
        'gmx.net', 'gmx.de', 'web.de', 'mail.com', 'proton.me', 'protonmail.com', 'zoho.com',
        'yandex.com', 'yandex.ru', 'mail.ru', 'wp.pl', 'o2.pl', 'onet.pl', 'interia.pl',
    ];

    /**
     * The unknown other-party addresses of these messages, once each.
     *
     * @param  array<int, array{from?: mixed, to?: mixed}>  $messages  each message's From and To, as lists of ['email' => ..., 'name' => ...]
     * @param  bool|null  $outgoing  true for messages known to be sent by you (a sent copy); null to tell by From
     * @return list<array{email: string, name: string}>
     */
    public function find(array $messages, ?User $user, ?MailAccount $account, ?bool $outgoing = null): array
    {
        $own = $this->ownAddresses($user, $account);
        $candidates = [];

        foreach (array_slice($messages, 0, 500) as $message) {
            $from = $this->addresses($message['from'] ?? []);
            $to = $this->addresses($message['to'] ?? []);
            $sent = $outgoing ?? array_intersect(array_keys($from), $own) !== [];

            foreach ($sent ? $to : $from as $email => $name) {
                if (! in_array($email, $own, true) && ! isset($candidates[$email])) {
                    $candidates[$email] = $name;
                }
            }
        }

        if ($candidates === []) {
            return [];
        }

        // live_value is set while a live record holds the address, whoever can
        // see it: a private contact of someone else's still counts as known.
        $known = EmailAddress::query()->whereIn('live_value', array_keys($candidates))->pluck('live_value')
            ->map(fn ($email): string => mb_strtolower((string) $email))->all();
        $accounts = MailAccount::query()->whereIn('email', array_keys($candidates))->pluck('email')
            ->map(fn ($email): string => mb_strtolower((string) $email))->all();

        return collect($candidates)
            ->reject(fn (string $name, string $email): bool => in_array($email, $known, true) || in_array($email, $accounts, true))
            ->take(self::MAX)
            ->map(fn (string $name, string $email): array => ['email' => $email, 'name' => $name])
            ->values()->all();
    }

    /**
     * First and last name from a display name: "Dahl, Maria" and "Maria Dahl"
     * both read as Maria / Dahl. With no name, a guess from the address's local
     * part (maria.dahl@ → Maria / Dahl). The user can correct either.
     *
     * @return array{first_name: string, last_name: string}
     */
    public static function splitName(string $name, string $email): array
    {
        $name = trim(preg_replace('/\s+/', ' ', trim($name, " \t\"'")) ?? '');

        if ($name === '' || mb_strtolower($name) === mb_strtolower($email)) {
            $local = Str::before($email, '@');
            $parts = array_values(array_filter(preg_split('/[._\-]+/', $local) ?: [], fn ($p): bool => $p !== '' && ! is_numeric($p)));

            if (count($parts) >= 2) {
                return ['first_name' => Str::ucfirst($parts[0]), 'last_name' => Str::ucfirst(implode(' ', array_slice($parts, 1)))];
            }

            return ['first_name' => '', 'last_name' => ''];
        }

        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));

            return ['first_name' => $first, 'last_name' => $last];
        }

        $words = explode(' ', $name);

        if (count($words) === 1) {
            return ['first_name' => '', 'last_name' => $words[0]];
        }

        return ['first_name' => array_shift($words), 'last_name' => implode(' ', $words)];
    }

    /**
     * Contacts this address probably belongs to, best first: the same name
     * (either order, any case), then people at the address's company domain.
     * The contact query keeps its visibility scope: only contacts the user
     * can see are offered.
     *
     * @return Collection<int, Contact>
     */
    public function suggestions(string $email, string $firstName, string $lastName, int $limit = 8): Collection
    {
        $first = mb_strtolower(trim($firstName));
        $last = mb_strtolower(trim($lastName));
        $found = collect();

        if ($last !== '') {
            $found = Contact::query()
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->whereRaw('LOWER(last_name) = ?', [$last])->when($first !== '', fn ($q) => $q->whereRaw('LOWER(first_name) = ?', [$first])))
                    ->when($first !== '', fn ($query) => $query->orWhere(fn ($q) => $q->whereRaw('LOWER(last_name) = ?', [$first])->whereRaw('LOWER(first_name) = ?', [$last]))))
                ->with('company')->limit($limit)->get();
        }

        $domain = static::domain($email);

        if ($domain !== null && $found->count() < $limit) {
            $companyIds = $this->companiesAt($domain)->pluck('id');
            $contactIds = EmailAddress::query()->where('owner_type', (new Contact)->getMorphClass())
                ->whereNotNull('live_value')->where('value', 'like', '%@'.$domain)->pluck('owner_id');

            $found = $found->concat(Contact::query()
                ->where(fn ($query) => $query->whereIn('company_id', $companyIds)->orWhereIn('id', $contactIds))
                ->whereKeyNot($found->map(fn ($contact) => $contact->getKey())->all())
                ->with('company')->orderBy('last_name')->orderBy('first_name')
                ->limit($limit - $found->count())->get());
        }

        return $found->values();
    }

    /** The company this address's domain belongs to, if exactly one does. */
    public function companyFor(string $email): ?Company
    {
        $domain = static::domain($email);

        if ($domain === null) {
            return null;
        }

        $companies = $this->companiesAt($domain);

        if ($companies->isEmpty()) {
            // A colleague at the same domain tells the company too.
            $companies = Contact::query()
                ->whereIn('id', EmailAddress::query()->where('owner_type', (new Contact)->getMorphClass())
                    ->whereNotNull('live_value')->where('value', 'like', '%@'.$domain)->select('owner_id'))
                ->whereNotNull('company_id')->with('company')->get()
                ->pluck('company')->filter()->unique('id')->values();
        }

        return $companies->count() === 1 ? $companies->first() : null;
    }

    /** A company name from the domain: siluett.se → Siluett. */
    public static function companyName(string $email): string
    {
        $domain = static::domain($email);

        return $domain === null ? '' : Str::headline(Str::beforeLast($domain, '.'));
    }

    /** The address's domain, or null for a free-mail one. */
    public static function domain(string $email): ?string
    {
        $domain = mb_strtolower(trim(Str::afterLast($email, '@')));

        return $domain === '' || ! str_contains($email, '@') || in_array($domain, self::FREE_MAIL, true) ? null : $domain;
    }

    /**
     * Companies with an e-mail address or a website at $domain, visible to the user.
     *
     * @return Collection<int, Company>
     */
    protected function companiesAt(string $domain): Collection
    {
        $morph = (new Company)->getMorphClass();
        $byEmail = EmailAddress::query()->where('owner_type', $morph)->whereNotNull('live_value')
            ->where('value', 'like', '%@'.$domain)->pluck('owner_id');
        $byWebsite = OnlineAccount::query()->where('owner_type', $morph)
            ->where(fn ($q) => $q->where('value', $domain)->orWhere('value', 'like', '%://'.$domain.'%')
                ->orWhere('value', 'like', '%.'.$domain.'%')->orWhere('value', 'like', $domain.'/%'))
            ->pluck('owner_id');

        return Company::query()->whereIn('id', $byEmail->concat($byWebsite)->unique())->get();
    }

    /**
     * Your own addresses: the mail account's and your contact's.
     *
     * @return list<string>
     */
    protected function ownAddresses(?User $user, ?MailAccount $account): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($email): string => mb_strtolower(trim((string) $email)),
            [$account?->email, $user?->email, ...($user?->contact?->collection('emails')->pluck('value')->all() ?? [])],
        ))));
    }

    /**
     * @return array<string, string> address => display name, valid addresses only
     */
    protected function addresses(mixed $list): array
    {
        $out = [];

        foreach (array_slice(is_array($list) ? $list : [], 0, 100) as $entry) {
            $email = mb_strtolower(trim((string) (is_array($entry) ? ($entry['email'] ?? '') : $entry)));

            if (filter_var($email, FILTER_VALIDATE_EMAIL) && ! isset($out[$email])) {
                $out[$email] = Str::limit(trim((string) (is_array($entry) ? ($entry['name'] ?? '') : '')), 150, '');
            }
        }

        return $out;
    }
}
