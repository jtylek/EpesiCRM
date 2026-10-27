<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Support\Demo;
use BackedEnum;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\CreateOAuthApp;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\EditOAuthApp;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\ListOAuthApps;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\ViewOAuthApp;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Schemas\OAuthAppForm;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Schemas\OAuthAppInfolist;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Tables\OAuthAppsTable;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\OAuth\OAuthProviders;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Administration → OAuth Apps: the apps this installation registered with
 * the OAuth providers, one record per provider and environment (production,
 * a sandbox), each with its client ID and secret and the exact redirect URI
 * to register it with. Each installation registers its own app, because the
 * redirect URI holds its own address. Super admins only, as the whole panel;
 * no policy, as for Themes and Users.
 *
 * The secret is never sent back to the browser: an empty field keeps it.
 * An app whose provider's module is gone keeps its row, shown as such, so
 * re-enabling the module needs no new setup.
 */
class OAuthAppResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = OAuthClient::class;

    protected static string|UnitEnum|null $navigationGroup = 'Server Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'OAuth Apps';

    protected static ?string $modelLabel = 'OAuth app';

    protected static ?string $pluralModelLabel = 'OAuth apps';

    // The old single settings page's address, which other modules link to.
    protected static ?string $slug = 'oauth-apps';

    public static function form(Schema $schema): Schema
    {
        return OAuthAppForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OAuthAppInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OAuthAppsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOAuthApps::route('/'),
            'create' => CreateOAuthApp::route('/create'),
            'view' => ViewOAuthApp::route('/{record}'),
            'edit' => EditOAuthApp::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record instanceof OAuthClient) {
            return parent::getRecordTitle($record);
        }

        return static::providerLabel($record->provider).' — '.static::environmentLabel($record->provider, $record->environment);
    }

    public static function providerLabel(?string $provider): string
    {
        if (blank($provider)) {
            return '';
        }

        $registered = OAuthProviders::find($provider);

        return $registered ? __($registered->label()) : $provider;
    }

    public static function environmentLabel(?string $provider, ?string $environment): string
    {
        $label = $provider ? (OAuthProviders::find($provider)?->environments()[$environment] ?? null) : null;

        return $label ? __($label) : (string) $environment;
    }

    /**
     * The environments of $provider that have no app yet, as key => label.
     *
     * @return array<string, string>
     */
    public static function freeEnvironments(?string $provider): array
    {
        $registered = $provider ? OAuthProviders::find($provider) : null;

        if (! $registered) {
            return [];
        }

        $used = OAuthClient::query()->where('provider', $provider)->pluck('environment')->all();

        return collect($registered->environments())
            ->except($used)
            ->map(fn (string $label): string => __($label))
            ->all();
    }

    /**
     * The registered providers with an environment that has no app yet, as
     * key => label: what a new app can be for.
     *
     * @return array<string, string>
     */
    public static function providersWithFreeEnvironments(): array
    {
        return collect(OAuthProviders::all())
            ->filter(fn ($provider, string $key): bool => static::freeEnvironments($key) !== [])
            ->map(fn ($provider): string => __($provider->label()))
            ->all();
    }

    /** The connected accounts that renew their tokens through $client's app. */
    public static function tokensUsing(OAuthClient $client): int
    {
        return OAuthToken::query()
            ->where('provider', $client->provider)
            ->where('environment', $client->environment)
            ->count();
    }

    /**
     * Every Delete of an app (list row, View, Edit): says what stops working
     * and does nothing in demo mode. The tokens stay, so entering the same
     * app again brings the connections back.
     */
    public static function configureDeleteAction(DeleteAction $action): DeleteAction
    {
        return Demo::guard($action->modalDescription(function (Model $record): string {
            $tokens = $record instanceof OAuthClient ? static::tokensUsing($record) : 0;

            return match (true) {
                $tokens === 0 => __('No connected account uses this app.'),
                $tokens === 1 => __('One connected account uses this app. Once its access token expires, it stops working until the app is entered again.'),
                default => __(':count connected accounts use this app. Once their access tokens expire, they stop working until the app is entered again.', ['count' => $tokens]),
            };
        }));
    }
}
