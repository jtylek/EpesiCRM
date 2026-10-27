<?php

namespace Epesi\Modules\OAuth\Console;

use Epesi\Modules\OAuth\Enums\TokenStatus;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\OAuth\OAuthProviders;
use Epesi\Modules\OAuth\Services\OAuthTokens;
use Illuminate\Console\Command;

/**
 * Renews OAuth access tokens shortly before they expire, so a refresh token
 * that the provider no longer accepts is found by cron, and its owner told,
 * rather than in the middle of someone's work. Run by cron every 15 minutes.
 */
class RefreshTokensCommand extends Command
{
    protected $signature = 'oauth:refresh';

    protected $description = 'Renew OAuth access tokens before they expire';

    public function handle(OAuthTokens $tokens): int
    {
        $ahead = (int) config('epesi-oauth.renew_ahead_minutes', 20) * 60;
        $renewed = 0;
        $failed = 0;

        $due = OAuthToken::query()
            ->where('status', TokenStatus::Active->value)
            ->whereNotNull('refresh_token')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '<=', now()->addSeconds($ahead)))
            ->orderBy('id')
            ->get();

        foreach ($due as $token) {
            if (! OAuthProviders::find($token->provider)) {
                // Its module is disabled: nothing here can renew it.
                continue;
            }

            try {
                $tokens->refresh($token, $ahead);
                $renewed++;
            } catch (OAuthException $e) {
                $failed++;
                $this->warn("#{$token->getKey()} ({$token->provider}): {$e->getMessage()}");
            }
        }

        $this->info("Renewed {$renewed} token(s), {$failed} failed.");

        return self::SUCCESS;
    }
}
