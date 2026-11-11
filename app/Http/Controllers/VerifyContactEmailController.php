<?php

namespace App\Http\Controllers;

use App\Filament\Portal\Pages\MyContact;
use App\Models\User;
use App\Support\Auth\NewAccountMailer;
use App\Support\Auth\PortalEmails;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;

/**
 * What the link e-mailed by PortalEmails::sendVerification() opens. The signed
 * URL is the proof — it only reached the mailbox being verified — so it needs
 * no sign-in; the portal then asks for one as usual.
 *
 * My Contact is in both the customer and the dealer portal, so the link goes
 * back to the one the address's login opens (the customer portal when it
 * opens neither).
 */
class VerifyContactEmailController extends Controller
{
    public function __invoke(int $email, string $hash): RedirectResponse
    {
        Filament::setCurrentPanel(self::panelFor($email));

        $notification = PortalEmails::verify($email, $hash)
            ? Notification::make()->title(__('E-mail address verified'))->success()
            : Notification::make()->title(__('This link is not valid any more'))->danger();

        $notification->send();

        return redirect(MyContact::getUrl());
    }

    protected static function panelFor(int $email): string
    {
        $owner = EmailAddress::query()->find($email)?->owner;
        $user = $owner instanceof Contact && $owner->user_id ? User::query()->find($owner->user_id) : null;
        $panel = $user ? NewAccountMailer::panelFor($user)?->getId() : null;

        return in_array($panel, ['portal', 'dealer'], true) ? $panel : 'portal';
    }
}
