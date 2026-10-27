<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Actions;

use App\Support\Demo;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\OAuthProviders;
use Epesi\Modules\OAuth\Providers\AppCheck;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\HtmlString;

/**
 * Test app on an OAuth app's View and Edit pages, for providers that can test
 * an app without anyone signing in (OAuthProvider::supportsAppTest()). It
 * tests the app as stored: on Edit, it says so before running, since the
 * form may hold changes not saved yet. The provider's checks store nothing;
 * the result lists each one and what the test doesn't prove.
 */
final class TestAppAction
{
    public static function make(bool $onEditPage = false): Action
    {
        $action = Action::make('testOAuthApp')
            ->label('Test app')
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->tooltip(__('Tests the app as saved, without signing in to an account.'))
            ->visible(fn (OAuthClient $record): bool => OAuthProviders::find((string) $record->provider)?->supportsAppTest() ?? false)
            ->action(fn (OAuthClient $record) => self::run($record));

        if ($onEditPage) {
            $action
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedSignal)
                ->modalHeading(__('Test app'))
                ->modalDescription(__('The test uses the app as saved. Changes on this form that are not saved yet are not tested: save them first.'))
                ->modalSubmitActionLabel(__('Test app'));
        }

        return Demo::guard($action);
    }

    public static function run(OAuthClient $record): void
    {
        // The stored row, whatever the page holds.
        $app = $record->fresh() ?? $record;
        $provider = OAuthProviders::find((string) $app->provider);

        if (! $provider?->supportsAppTest()) {
            return;
        }

        if (! $app->isConfigured()) {
            $checks = [AppCheck::fail(__('Client ID and secret'), __('No client ID or secret is stored for this app. Enter both and save, then test again.'))];
        } else {
            try {
                $checks = $provider->testApp($app);
            } catch (OAuthException $e) {
                $checks = [AppCheck::fail(__('App test'), $e->getMessage())];
            } catch (ConnectionException) {
                $checks = [AppCheck::fail(__('App test'), __(':provider could not be reached. Try again later.', ['provider' => $provider->label()]))];
            }
        }

        $failed = $checks === [] || array_filter($checks, fn (AppCheck $check): bool => ! $check->passed) !== [];

        $lines = array_map(
            fn (AppCheck $check): string => '<strong>'.e($check->label).'</strong> ('.e($check->passed ? __('passed') : __('failed')).'): '.e($check->message),
            $checks,
        );
        $lines[] = '<em>'.e(__($provider->appTestLimits())).'</em>';

        Notification::make()
            ->title($failed ? __('App test failed') : __('The app works'))
            ->body(new HtmlString(implode('<br>', $lines)))
            ->status($failed ? 'danger' : 'success')
            ->persistent($failed)
            ->send();
    }
}
