<?php

namespace Epesi\Modules\OAuth\Filament\Actions;

use App\Models\User;
use App\Support\Demo;
use Closure;
use Epesi\Modules\OAuth\Contracts\OAuthOwner;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\OAuth\OAuthProviders;
use Epesi\Modules\OAuth\Services\OAuthFlow;
use Epesi\Modules\OAuth\Services\OAuthTokens;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The "Connect" and "Disconnect" buttons an owner's page puts next to its
 * settings. Each closure receives the record: $provider names its provider
 * (null hides the buttons), $returnUrl where the callback comes back to (by
 * default the page the button is on), $scopes the scopes to ask for (null for
 * the provider's defaults; connecting again with more widens the same token),
 * $environment the app to use ("production" by default).
 *
 *     ConnectOAuthAction::connect(fn (MailAccount $record) => $record->oauthProvider(),
 *         returnUrl: fn (MailAccount $record) => EditMailAccount::getUrl(['record' => $record]))
 */
class ConnectOAuthAction
{
    /**
     * @param  Closure(Model): ?string  $provider
     * @param  (Closure(Model): ?string)|null  $returnUrl
     * @param  (Closure(Model): ?list<string>)|null  $scopes
     * @param  (Closure(Model): string)|null  $environment
     */
    public static function connect(Closure $provider, ?Closure $returnUrl = null, ?Closure $scopes = null, ?Closure $environment = null, string $name = 'connectOAuth'): Action
    {
        return Demo::guard(Action::make($name)
            ->label(fn (?Model $record): string => static::token($record, $provider) ? __('Connect again') : __('Connect account'))
            ->icon(Heroicon::OutlinedKey)
            ->color(fn (?Model $record): string => static::token($record, $provider)?->needsReconnect() === false ? 'gray' : 'primary')
            ->visible(fn (?Model $record): bool => static::allowed($record, $provider))
            ->action(function (Model $record, Component $livewire) use ($provider, $returnUrl, $scopes, $environment): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    $url = app(OAuthFlow::class)->start(
                        $record,
                        (string) $provider($record),
                        $user,
                        $returnUrl ? $returnUrl($record) : request()->header('referer'),
                        $scopes ? $scopes($record) : null,
                        $environment ? $environment($record) : OAuthClient::PRODUCTION,
                    );
                } catch (OAuthException $e) {
                    Notification::make()->title(__('Not connected'))->body($e->getMessage())->danger()->persistent()->send();

                    return;
                } catch (AuthorizationException) {
                    abort(403);
                }

                $livewire->redirect($url);
            }));
    }

    /**
     * @param  Closure(Model): ?string  $provider
     */
    public static function disconnect(Closure $provider, string $name = 'disconnectOAuth'): Action
    {
        return Demo::guard(Action::make($name)
            ->label('Disconnect')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('epesi forgets the connection and can no longer sign in with it until it is connected again.'))
            ->visible(fn (?Model $record): bool => static::allowed($record, $provider) && static::token($record, $provider) !== null)
            ->action(function (Model $record) use ($provider): void {
                /** @var User $user */
                $user = Auth::user();
                abort_unless($record instanceof OAuthOwner && $record->mayConnectOAuth($user), 403);

                app(OAuthTokens::class)->disconnect($record, (string) $provider($record));

                Notification::make()->title(__('Disconnected'))->success()->send();
            }));
    }

    /**
     * @param  Closure(Model): ?string  $provider
     */
    protected static function allowed(?Model $record, Closure $provider): bool
    {
        $user = Auth::user();
        $key = $record ? $provider($record) : null;

        return $record instanceof OAuthOwner
            && $record->exists
            && $user instanceof User
            && $key !== null
            && OAuthProviders::find($key) !== null
            && $record->mayConnectOAuth($user);
    }

    protected static function token(?Model $record, Closure $provider): ?OAuthToken
    {
        $key = $record ? $provider($record) : null;

        return $key && method_exists($record, 'oauthToken') ? $record->oauthToken($key) : null;
    }
}
