<?php

namespace Epesi\Modules\OAuth\Models;

use App\Casts\Encrypted;
use App\Models\User;
use Epesi\Modules\OAuth\Enums\TokenStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One owner's tokens from one provider, in one environment (production or
 * a sandbox: a module picks its API host by it). Both tokens are encrypted
 * at rest; read them through OAuthTokens::accessToken(), which renews them,
 * rather than from here.
 *
 * @property TokenStatus $status
 */
class OAuthToken extends Model
{
    protected $table = 'epesi_oauth_tokens';

    protected $fillable = [
        'provider',
        'environment',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'status',
        'last_error',
        'account',
        'connected_by',
        'refreshed_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $attributes = [
        'status' => 'active',
        'environment' => OAuthClient::PRODUCTION,
    ];

    protected function casts(): array
    {
        return [
            'access_token' => Encrypted::class,
            'refresh_token' => Encrypted::class,
            'expires_at' => 'datetime',
            'refreshed_at' => 'datetime',
            'scopes' => 'array',
            'status' => TokenStatus::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function needsReconnect(): bool
    {
        return $this->status === TokenStatus::NeedsReconnect;
    }

    /** Whether the access token is missing, or expires within $seconds. */
    public function expiresWithin(int $seconds): bool
    {
        if (blank($this->access_token)) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(now()->addSeconds($seconds));
    }
}
