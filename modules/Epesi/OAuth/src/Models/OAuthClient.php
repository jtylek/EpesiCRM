<?php

namespace Epesi\Modules\OAuth\Models;

use App\Casts\Encrypted;
use Epesi\Modules\OAuth\OAuthProviders;
use Illuminate\Database\Eloquent\Model;

/**
 * The app this installation registered with one provider in one environment
 * (Administration → OAuth Apps): its client ID and secret, the Microsoft
 * tenant where the provider has one, and the provider's own app settings
 * (`settings`, e.g. Allegro's User-Agent). Production and a sandbox are separate
 * apps with separate credentials, so an installation can hold both.
 */
class OAuthClient extends Model
{
    public const PRODUCTION = 'production';

    protected $table = 'epesi_oauth_clients';

    protected $fillable = ['provider', 'environment', 'client_id', 'client_secret', 'tenant', 'settings'];

    protected $hidden = ['client_secret'];

    protected $attributes = [
        'environment' => self::PRODUCTION,
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => Encrypted::class,
            'settings' => 'array',
        ];
    }

    /**
     * One of the settings the provider declares for its apps
     * (OAuthProvider::appFields()): the stored value, else the field's
     * default, else null.
     */
    public function setting(string $key): ?string
    {
        $value = $this->settings[$key] ?? null;

        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return OAuthProviders::find((string) $this->provider)?->appField($key)?->default;
    }

    public static function for(string $provider, string $environment = self::PRODUCTION): ?self
    {
        return static::query()->where('provider', $provider)->where('environment', $environment)->first();
    }

    public function isConfigured(): bool
    {
        return filled($this->client_id) && filled($this->client_secret);
    }
}
