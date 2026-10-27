<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Schemas;

use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\OAuthProviders;
use Epesi\Modules\OAuth\Providers\AppField;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class OAuthAppInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextEntry::make('provider')
                ->label('Provider')
                ->formatStateUsing(fn (OAuthClient $record): string => OAuthAppResource::providerLabel($record->provider)),
            TextEntry::make('environment')
                ->label('Environment')
                ->formatStateUsing(fn (OAuthClient $record): string => OAuthAppResource::environmentLabel($record->provider, $record->environment)),
            ...OAuthAppForm::registrationEntries(),
            TextEntry::make('client_id')
                ->label('Client ID')
                ->fontFamily(FontFamily::Mono)
                ->copyable()
                ->placeholder('—'),
            // Only whether there is one: the secret itself never leaves the server.
            TextEntry::make('secret_status')
                ->label('Client secret')
                ->state(fn (OAuthClient $record): string => filled($record->client_secret) ? __('Stored') : __('Not set'))
                ->badge()
                ->color(fn (OAuthClient $record): string => filled($record->client_secret) ? 'success' : 'danger'),
            TextEntry::make('tenant')
                ->label('Tenant ID')
                ->placeholder('—')
                ->visible(fn (OAuthClient $record): bool => filled($record->tenant)),
            // The provider's own app settings, the default shown when none is stored.
            Group::make()
                ->key('app_fields')
                ->schema(fn (OAuthClient $record): array => array_map(
                    fn (AppField $field): TextEntry => TextEntry::make('app_field_'.$field->key)
                        ->label(__($field->label))
                        ->state(fn (OAuthClient $record): ?string => $record->setting($field->key))
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->placeholder('—')
                        ->helperText($field->help ? __($field->help) : null),
                    OAuthProviders::find($record->provider)?->appFields() ?? [],
                )),
            IconEntry::make('configured')
                ->label('Configured')
                ->state(fn (OAuthClient $record): bool => $record->isConfigured())
                ->boolean(),
            TextEntry::make('connections')
                ->label('Connected accounts')
                ->state(fn (OAuthClient $record): int => OAuthAppResource::tokensUsing($record)),
        ]);
    }
}
