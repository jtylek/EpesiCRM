<?php

namespace Epesi\Modules\Mail\Services;

use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Which CRM records an e-mail address belongs to — CRM_MailCommon::
 * look_contact(): every EmailAddress item whose value matches, whichever
 * record owns it.
 *
 * Visibility is deliberately not applied: linking a message to a private
 * contact reveals nothing, since only people who can open that contact see
 * its E-mails tab.
 */
class ContactMatcher
{
    /**
     * @param  array<int, string>  $emails
     * @return Collection<int, Model>
     */
    public function match(array $emails): Collection
    {
        $emails = array_values(array_unique(array_filter(array_map(
            fn (string $e): string => mb_strtolower(trim($e)),
            $emails,
        ))));

        if ($emails === []) {
            return collect();
        }

        // The owning record without the ownership scope: the Mailbox
        // archives while someone is signed in, and the scope would hide
        // another user's private contact here.
        return EmailAddress::query()->whereIn('value', $emails)
            ->with(['owner' => fn ($query) => $query->withoutGlobalScope('ownership')])
            ->get()
            ->pluck('owner')->filter()
            ->unique(fn (Model $m): string => $m->getMorphClass().':'.$m->getKey())
            ->values();
    }

    /**
     * Link already-archived mail to $record once it gains $email — what
     * CRM_MailCommon::reload_mails() did when an address was added. Matches
     * whole addresses only, so "ann@x.com" never catches "joann@x.com".
     */
    public function relinkExisting(Model $record, string $email): int
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return 0;
        }

        $linked = 0;

        Mail::query()
            ->where(fn ($q) => $q->where('from', 'like', "%{$email}%")
                ->orWhere('to', 'like', "%{$email}%")
                ->orWhere('cc', 'like', "%{$email}%"))
            ->each(function (Mail $mail) use ($record, $email, &$linked): void {
                if (! in_array($email, $mail->addresses(), true)) {
                    return;
                }

                $mail->linkTo($record);
                $linked++;
            });

        return $linked;
    }
}
