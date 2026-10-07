<?php

namespace Epesi\Modules\Store\Models;

use App\Casts\Encrypted;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for this install's store connection. A table rather than
 * config/, because it is written through the GUI and by the store's answers — a
 * module can't edit the app's config files, and shouldn't try. The store's
 * address itself is StoreClient::API_URL.
 *
 * Registration: the installation identifies itself with instance_uuid and
 * instance_secret (made here, at the first Register click). registration_status
 * mirrors what the store last said: unregistered, pending (the administrator
 * hasn't confirmed the e-mail yet), registered, or revoked. The licence key is
 * issued by the store when the e-mail is confirmed and picked up from there.
 */
class StoreSetting extends Model
{
    public const UNREGISTERED = 'unregistered';

    public const PENDING = 'pending';

    public const REGISTERED = 'registered';

    public const REVOKED = 'revoked';

    protected $table = 'epesi_store_settings';

    protected $fillable = [
        'licence_key',
        'instance_uuid',
        'instance_secret',
        'registration_status',
        'registered_email',
        'registered_url',
        'registered_at',
        'url_mismatch',
        'transfer_pending',
        'diagnostics',
        'web_server',
        'latest_core_version',
        'latest_core_security',
        'notified_version',
        'last_check_at',
    ];

    protected $attributes = [
        'registration_status' => self::UNREGISTERED,
        'url_mismatch' => false,
        'transfer_pending' => false,
        'diagnostics' => false,
        'latest_core_security' => false,
    ];

    protected $hidden = ['instance_secret'];

    protected function casts(): array
    {
        return [
            'instance_secret' => Encrypted::class,
            'registered_at' => 'datetime',
            'last_check_at' => 'datetime',
            'url_mismatch' => 'boolean',
            'transfer_pending' => 'boolean',
            'diagnostics' => 'boolean',
            'latest_core_security' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], []);
    }

    public function isRegistered(): bool
    {
        return $this->registration_status === self::REGISTERED;
    }

    public function isPending(): bool
    {
        return $this->registration_status === self::PENDING;
    }

    public function hasIdentity(): bool
    {
        return filled($this->instance_uuid) && filled($this->instance_secret);
    }
}
