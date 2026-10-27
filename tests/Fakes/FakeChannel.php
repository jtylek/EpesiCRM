<?php

namespace Tests\Fakes;

use App\Models\User;
use Epesi\Modules\OAuth\Concerns\HasOAuthTokens;
use Epesi\Modules\OAuth\Contracts\OAuthOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business record that owns a token, as a sales channel will: no user of
 * its own, connected by an administrator, and with no OAuthRecipients, so
 * the super admins hear when it needs connecting again.
 */
class FakeChannel extends Model implements OAuthOwner
{
    use HasOAuthTokens;

    protected $table = 'test_oauth_channels';

    protected $guarded = [];

    public static function setUpTable(): void
    {
        Schema::create('test_oauth_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Relation::morphMap(['test_oauth_channel' => static::class]);
    }

    public function mayConnectOAuth(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function oauthLabel(): string
    {
        return 'the channel '.$this->name;
    }

    public function oauthLoginHint(): ?string
    {
        return null;
    }

    public function oauthUrl(): ?string
    {
        return null;
    }
}
