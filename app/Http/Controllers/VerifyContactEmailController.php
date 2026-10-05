<?php

namespace App\Http\Controllers;

use App\Filament\Portal\Pages\MyContact;
use App\Support\Auth\PortalEmails;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;

/**
 * What the link e-mailed by PortalEmails::sendVerification() opens. The signed
 * URL is the proof — it only reached the mailbox being verified — so it needs
 * no sign-in; the portal then asks for one as usual.
 */
class VerifyContactEmailController extends Controller
{
    public function __invoke(int $email, string $hash): RedirectResponse
    {
        Filament::setCurrentPanel('portal');

        $notification = PortalEmails::verify($email, $hash)
            ? Notification::make()->title(__('E-mail address verified'))->success()
            : Notification::make()->title(__('This link is not valid any more'))->danger();

        $notification->send();

        return redirect(MyContact::getUrl());
    }
}
