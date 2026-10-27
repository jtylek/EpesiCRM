<?php

namespace Epesi\Modules\OAuth\Concerns;

use Epesi\Modules\OAuth\Models\OAuthToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The tokens an OAuthOwner holds, deleted with it.
 *
 * @mixin Model
 */
trait HasOAuthTokens
{
    public static function bootHasOAuthTokens(): void
    {
        static::deleting(function (Model $owner): void {
            // Soft-deleted owners keep theirs: they may be restored.
            if (! method_exists($owner, 'isForceDeleting') || $owner->isForceDeleting()) {
                $owner->oauthTokens()->delete();
            }
        });
    }

    /**
     * @return MorphMany<OAuthToken, $this>
     */
    public function oauthTokens(): MorphMany
    {
        return $this->morphMany(OAuthToken::class, 'owner');
    }

    /** The token from $provider, or the owner's only one when no provider is named. */
    public function oauthToken(?string $provider = null): ?OAuthToken
    {
        return $this->oauthTokens()
            ->when($provider !== null, fn ($query) => $query->where('provider', $provider))
            ->orderByDesc('id')
            ->first();
    }
}
