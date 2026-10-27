<?php

namespace Epesi\Modules\OAuth\Http\Controllers;

use App\Support\Demo;
use Epesi\Modules\OAuth\Services\OAuthFlow;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /oauth/callback — where every provider sends the browser back. It says
 * how connecting went on the page the connect was started from.
 */
class CallbackController
{
    public function __invoke(Request $request, OAuthFlow $flow): RedirectResponse
    {
        // Connecting stores credentials every later demo visitor would share.
        abort_if(Demo::enabled(), 404);

        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $result = $flow->complete($request, $user);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        if ($result['error'] !== null) {
            Notification::make()->title(__('Not connected'))->body($result['error'])->danger()->persistent()->send();
        } else {
            $account = $result['token']?->account;

            Notification::make()
                ->title(__('Connected'))
                ->body($account ? __('Signed in as :account.', ['account' => $account]) : null)
                ->success()
                ->send();
        }

        return redirect()->to($result['return_url']);
    }
}
