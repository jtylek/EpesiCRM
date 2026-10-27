<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Tables;

use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\OAuthProviders;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class OAuthAppsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderBy('provider')->orderBy('environment'))
            ->columns([
                TextColumn::make('provider')
                    ->label(__('Provider'))
                    ->formatStateUsing(fn (OAuthClient $record): string => OAuthAppResource::providerLabel($record->provider))
                    ->sortable(),
                TextColumn::make('environment')
                    ->label(__('Environment'))
                    ->formatStateUsing(fn (OAuthClient $record): string => OAuthAppResource::environmentLabel($record->provider, $record->environment))
                    ->sortable(),
                TextColumn::make('client_id')
                    ->label(__('Client ID'))
                    ->fontFamily(FontFamily::Mono)
                    ->limit(40)
                    ->placeholder('—')
                    ->searchable(),
                // Whether one is stored, never the secret itself.
                IconColumn::make('has_secret')
                    ->label(__('Secret stored'))
                    ->state(fn (OAuthClient $record): bool => filled($record->client_secret))
                    ->boolean(),
                TextColumn::make('tenant')
                    ->label(__('Tenant ID'))
                    ->placeholder('—'),
                IconColumn::make('configured')
                    ->label(__('Configured'))
                    ->state(fn (OAuthClient $record): bool => $record->isConfigured())
                    ->boolean(),
                IconColumn::make('provider_available')
                    ->label(__('Provider available'))
                    ->state(fn (OAuthClient $record): bool => OAuthProviders::find($record->provider) !== null)
                    ->boolean(),
            ])
            ->emptyStateHeading(__('No OAuth apps yet'))
            ->emptyStateDescription(__('Add the app you registered with a provider, once for each environment you use.'))
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip(__('View')),
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                OAuthAppResource::configureDeleteAction(DeleteAction::make())
                    ->iconButton()
                    ->tooltip(__('Delete')),
            ]);
    }
}
