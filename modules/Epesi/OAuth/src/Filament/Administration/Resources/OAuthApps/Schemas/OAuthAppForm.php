<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Schemas;

use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\OAuthProviders;
use Epesi\Modules\OAuth\Providers\AppField;
use Epesi\Modules\OAuth\Providers\MicrosoftProvider;
use Epesi\Modules\OAuth\Providers\OAuthProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Create picks the provider and environment, which stay fixed afterwards:
 * the tokens connected through an app remember both. Edit shows them as text.
 */
class OAuthAppForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('provider')
                ->label('Provider')
                ->options(fn (): array => OAuthAppResource::providersWithFreeEnvironments())
                // Offered: providers with a free environment; accepted: any registered one, so a
                // taken combination gets the unique rule's message below rather than "invalid".
                ->in(fn (): array => array_keys(OAuthProviders::all()))
                ->required()
                ->live()
                ->afterStateUpdated(function (Select $component, Set $set, ?string $state): void {
                    $free = OAuthAppResource::freeEnvironments($state);
                    $set('environment', count($free) === 1 ? array_key_first($free) : null);
                    // The provider's app settings appear with their defaults, so the common case needs no typing.
                    $component->getContainer()->getComponent('app_fields')?->getChildSchema()?->fill();
                })
                ->visibleOn('create'),
            Select::make('environment')
                ->label('Environment')
                ->options(fn (Get $get): array => OAuthAppResource::freeEnvironments($get('provider')))
                ->in(fn (Get $get): array => array_keys(OAuthProviders::find((string) $get('provider'))?->environments() ?? []))
                ->required()
                ->unique(column: 'environment', modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('provider', $get('provider')))
                ->validationMessages(['unique' => __('This provider already has an app in this environment. Edit that one instead.')])
                ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && filled($get('provider'))),
            TextEntry::make('provider_label')
                ->label('Provider')
                ->state(fn (?Model $record): string => OAuthAppResource::providerLabel($record?->provider))
                ->visibleOn('edit'),
            TextEntry::make('environment_label')
                ->label('Environment')
                ->state(fn (?Model $record): string => OAuthAppResource::environmentLabel($record?->provider, $record?->environment))
                ->visibleOn('edit'),
            ...static::registrationEntries(),
            TextInput::make('client_id')
                ->label('Client ID')
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),
            TextInput::make('client_secret')
                ->label('Client secret')
                ->password()
                ->revealable()
                ->maxLength(1000)
                ->required(fn (string $operation): bool => $operation === 'create')
                // Never sent to the browser; an empty field keeps the stored secret.
                ->formatStateUsing(fn (): ?string => null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (string $state): string => trim($state))
                ->placeholder(fn (?Model $record): ?string => filled($record?->client_secret) ? __('Leave empty to keep') : null),
            TextInput::make('tenant')
                ->label('Tenant ID')
                ->maxLength(255)
                ->regex('/^[A-Za-z0-9][A-Za-z0-9.-]*$/')
                ->placeholder(MicrosoftProvider::DEFAULT_TENANT)
                ->helperText(__('Your directory (tenant) ID or domain, for an app registered for your organization only. Empty: any account.'))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                ->visible(fn (Get $get, ?Model $record): bool => (bool) static::provider($get, $record)?->usesTenant()),
            Group::make()
                ->key('app_fields')
                ->schema(fn (Get $get, ?Model $record): array => array_map(
                    static::appFieldInput(...),
                    static::provider($get, $record)?->appFields() ?? [],
                )),
        ]);
    }

    /** An input for one of the provider's app settings, stored under `settings.<key>`. */
    protected static function appFieldInput(AppField $field): TextInput
    {
        return TextInput::make('settings.'.$field->key)
            ->label(__($field->label))
            ->required($field->required)
            ->maxLength($field->maxLength)
            ->default($field->default)
            // An app saved before the field existed shows the value in effect: the default.
            ->formatStateUsing(fn (?string $state): ?string => filled($state) ? $state : $field->default)
            ->placeholder($field->default)
            ->helperText($field->help ? __($field->help) : null)
            // Empty is stored as null, so the field's default applies (OAuthClient::setting()).
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null);
    }

    /**
     * What to register at the provider: the redirect URI, where, and how.
     * Shown on Create once a provider is picked, and on Edit.
     *
     * @return list<TextEntry>
     */
    public static function registrationEntries(): array
    {
        return [
            TextEntry::make('unavailable')
                ->hiddenLabel()
                ->state(__('This provider is not available: the module that adds it is disabled or uninstalled.'))
                ->color('danger')
                ->visible(fn (?Model $record): bool => $record instanceof OAuthClient && ! OAuthProviders::find($record->provider)),
            // The providers' help texts refer to "the redirect URI below".
            TextEntry::make('setup_help')
                ->label('How to set it up')
                ->state(fn (Get $get, ?Model $record): ?string => ($help = static::provider($get, $record)?->setupHelp()) ? __($help) : null)
                ->visible(fn (Get $get, ?Model $record): bool => filled(static::provider($get, $record)?->setupHelp())),
            TextEntry::make('console_url')
                ->label('Where to register the app')
                ->state(fn (Get $get, ?Model $record): ?string => static::provider($get, $record)?->consoleUrl())
                ->url(fn (Get $get, ?Model $record): ?string => static::provider($get, $record)?->consoleUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Get $get, ?Model $record): bool => filled(static::provider($get, $record)?->consoleUrl())),
            TextEntry::make('redirect_uri')
                ->label('Redirect URI')
                ->state(fn (Get $get, ?Model $record): ?string => ($provider = static::provider($get, $record)) ? OAuthProviders::redirectUri($provider) : null)
                ->fontFamily(FontFamily::Mono)
                ->copyable()
                ->helperText(__('Register exactly this address with the app.'))
                ->visible(fn (Get $get, ?Model $record): bool => static::provider($get, $record) !== null),
        ];
    }

    /** The record's provider on Edit and View, the one picked on Create. */
    protected static function provider(Get $get, ?Model $record): ?OAuthProvider
    {
        $key = $record instanceof OAuthClient ? $record->provider : $get('provider');

        return filled($key) ? OAuthProviders::find((string) $key) : null;
    }
}
