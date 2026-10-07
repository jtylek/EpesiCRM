<?php

namespace Epesi\Modules\Store\Filament\Pages;

use App\Filament\Administration\Pages\DatabaseUpdate;
use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Module;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use App\Services\Update\CoreUpdater;
use App\Services\Update\UpdateException;
use App\Support\Modules\VersionConstraint;
use App\Support\Version;
use BackedEnum;
use Epesi\Modules\Store\Models\StoreSetting;
use Epesi\Modules\Store\Services\Diagnostics;
use Epesi\Modules\Store\Services\Registration;
use Epesi\Modules\Store\Services\StoreApiException;
use Epesi\Modules\Store\Services\StoreClient;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Browse a store server's catalog and install or update from it.
 *
 * The catalog is advisory only: what it says decides which rows are *offered*,
 * while the package itself is checksum-verified on download and then put through
 * the core's own ModuleArchive validation by ModuleInstaller. A compromised
 * store can therefore mislead this screen, but it cannot get an archive past the
 * installer that a hand-uploaded one wouldn't also have to pass.
 *
 * The store serves registered installations (Registration): until this one is
 * registered the page offers registration instead of the catalog.
 */
class Store extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    // Pinned second in the sidebar, after About (a sort below -1 pins an item).
    protected static ?int $navigationSort = -4;

    protected string $view = 'epesi-store::store';

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $catalog = null;

    protected ?string $catalogError = null;

    /** @var array<string, mixed>|null the core release the store offers */
    protected ?array $core = null;

    public function mount(): void
    {
        Diagnostics::rememberWebServer();

        // The e-mail may have been confirmed since: pick the licence key up now.
        if ($this->setting()->isPending()) {
            app(Registration::class)->refreshStatus();
        }
    }

    public function getTitle(): string
    {
        return __('Epesi Store');
    }

    public static function getNavigationLabel(): string
    {
        return __('Epesi Store');
    }

    public function setting(): StoreSetting
    {
        return StoreSetting::current();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->updateCoreAction(),
            $this->settingsAction(),
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    app(Registration::class)->checkIn();
                    $this->catalog = null;
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect($this->records()))
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (array $record): ?string => $record['description'] ?? null)
                    ->wrap(),
                TextColumn::make('category')
                    ->placeholder(__('-')),
                TextColumn::make('price_label')
                    ->label('Price'),
                TextColumn::make('version')
                    ->label('Latest'),
                TextColumn::make('installed_version')
                    ->label('Installed')
                    ->placeholder(__('-')),
                TextColumn::make('status_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn (array $record): string => match ($record['status']) {
                        'installed' => 'success',
                        'update' => 'warning',
                        'incompatible', 'unlicensed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                $this->installAction(),
                $this->buyAction(),
            ])
            // Closures, not values: table() is configured before records() has
            // run, so a fetch error isn't known yet at this point.
            ->emptyStateHeading(fn (): string => $this->catalogError ?? 'Nothing in the catalog')
            ->emptyStateDescription(fn (): string => $this->catalogError
                ? __('Check that this server can reach the internet, then click Refresh.')
                : __('The store has no modules published yet.'))
            ->paginated(false);
    }

    /** Opens the store's registration form, filled in with what epesi knows. */
    public function registerAction(): Action
    {
        return Action::make('register')
            ->label(fn (): string => $this->setting()->isPending() ? __('Open the registration form') : __('Register Epesi'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color(fn (): string => $this->setting()->isPending() ? 'gray' : 'primary')
            ->action(function (): void {
                try {
                    $url = app(Registration::class)->start(auth()->user());
                } catch (ModuleException $exception) {
                    Notification::make()->danger()->title(__('The Epesi Store could not be reached'))->body($exception->getMessage())->send();

                    return;
                }

                $this->redirect($url);
            });
    }

    /** The installation moved: the registered e-mail confirms moving the licence here. */
    public function transferAction(): Action
    {
        return Action::make('transfer')
            ->label('Move the licence here')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => __('The licence is registered to :old. A confirmation link goes to :email; once it is clicked, the licence belongs to :new and :old loses it.', [
                'old' => $this->setting()->registered_url,
                'new' => StoreClient::installationUrl(),
                'email' => $this->setting()->registered_email,
            ]))
            ->action(function (): void {
                try {
                    app(Registration::class)->requestTransfer();
                } catch (ModuleException $exception) {
                    Notification::make()->danger()->title(__('Not requested'))->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('Confirmation sent to :email', ['email' => $this->setting()->registered_email]))->send();
            });
    }

    /**
     * A paid module this installation doesn't hold yet: the store's own buy page
     * (signed for this installation, so the licence is bound to it and its URL).
     * Back here, Refresh shows the module as available once the payment is through.
     */
    protected function buyAction(): Action
    {
        return Action::make('buy')
            ->label('Buy')
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color('success')
            ->visible(fn (array $record): bool => $record['status'] === 'unlicensed' && filled($record['buy_url'] ?? null))
            ->url(fn (array $record): ?string => $record['buy_url'] ?? null, shouldOpenInNewTab: true);
    }

    protected function installAction(): Action
    {
        return Action::make('install')
            ->label(fn (array $record): string => $record['status'] === 'update' ? __('Update') : __('Install'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (array $record): bool => in_array($record['status'], ['available', 'update'], true))
            ->requiresConfirmation()
            ->modalDescription(fn (array $record): string => "{$record['id']} {$record['version']} will be downloaded from the store and installed. It runs with the same privileges as the application itself.")
            ->action(function (array $record): void {
                if (! config('modules.install_enabled')) {
                    Notification::make()->danger()->title(__('Module installation is disabled'))->send();

                    return;
                }

                $client = app(StoreClient::class);
                $file = null;

                try {
                    $file = $client->download($record['download_url'], $record['sha256']);
                    $module = app(ModuleInstaller::class)->installFromZip($file, allowUpdate: true);
                } catch (ModuleException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('Not installed'))
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    return;
                } finally {
                    if ($file && is_file($file)) {
                        unlink($file);
                    }
                }

                Notification::make()
                    ->success()
                    ->title(__('Installed :module :version', ['module' => $module->name, 'version' => $module->version]))
                    ->send();

                // A full page load rather than a Livewire re-render: the table's
                // records are resolved before this action runs, so the row would
                // otherwise still read "available", and the new module's screens
                // only appear once the panel is rebuilt on a fresh request.
                $this->redirect(static::getUrl());
            });
    }

    /**
     * The store's core release, when it is newer than this installation.
     *
     * @return array<string, mixed>|null
     */
    public function coreUpdate(): ?array
    {
        $this->records();

        return $this->core !== null && version_compare((string) $this->core['version'], Version::current(), '>')
            ? $this->core
            : null;
    }

    /**
     * Updates epesi itself: the release is downloaded, checksum-verified and
     * validated by CoreUpdater, which backs up what it replaces and rolls back
     * on failure. The database is migrated afterwards, from Database update,
     * with the new code.
     */
    protected function updateCoreAction(): Action
    {
        return Action::make('updateCore')
            ->label(fn (): string => __('Update epesi to :version', ['version' => $this->coreUpdate()['version'] ?? '']))
            ->icon(Heroicon::OutlinedArrowUpCircle)
            ->color(fn (): string => ($this->coreUpdate()['security'] ?? false) ? 'danger' : 'warning')
            ->visible(fn (): bool => $this->coreUpdate() !== null)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => 'epesi '.($this->coreUpdate()['version'] ?? '').' ('.round(($this->coreUpdate()['size'] ?? 0) / 1048576, 1).' MB) will be downloaded from the store and put over this installation, which is at '.Version::current().'. The files it replaces are kept as a backup, and the update is undone if it fails part-way. Afterwards you update the database. Take a database backup first. It runs with the same privileges as the application itself.')
            ->action(function (): void {
                if (! config('modules.install_enabled')) {
                    Notification::make()->danger()->title(__('Updating is disabled'))->body(__('Set MODULES_INSTALL_ENABLED=true in .env to allow updates from the browser.'))->send();

                    return;
                }

                $core = $this->coreUpdate();
                $file = null;

                try {
                    $file = app(StoreClient::class)->download($core['download_url'], $core['sha256']);
                    $result = app(CoreUpdater::class)->apply($file);
                } catch (ModuleException|UpdateException $exception) {
                    Notification::make()->danger()->title(__('Not updated'))->body($exception->getMessage())->persistent()->send();

                    return;
                } finally {
                    if ($file && is_file($file)) {
                        unlink($file);
                    }
                }

                Notification::make()
                    ->success()
                    ->title(__('epesi updated to :version', ['version' => $result['to']]))
                    ->body(__('The files are in place. Now update the database.'))
                    ->persistent()
                    ->send();

                $this->redirect(DatabaseUpdate::getUrl());
            });
    }

    /** The registration as the store knows it, and "Delete my registration". */
    protected function settingsAction(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('settings')
                ->label('Store settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->modalHeading(__('Store settings'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->schema([
                    TextEntry::make('licence_key')
                        ->label('Licence key')
                        ->state(fn (): ?string => $this->setting()->licence_key)
                        ->copyable()
                        ->fontFamily('mono')
                        ->placeholder(__('Issued when the registration is confirmed')),
                    TextEntry::make('registered_email')
                        ->label('Registered e-mail')
                        ->state(fn (): ?string => $this->setting()->registered_email)
                        ->placeholder(__('-')),
                    TextEntry::make('registered_url')
                        ->label('Licensed URL')
                        ->state(fn (): ?string => $this->setting()->registered_url ?? StoreClient::installationUrl()),
                    TextEntry::make('diagnostics')
                        ->label('Diagnostic data')
                        ->state(fn (): string => $this->setting()->diagnostics ? __('Sent daily, encrypted') : __('Not sent')),
                ]),
            Action::make('deleteRegistration')
                ->label('Delete registration')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => $this->setting()->hasIdentity())
                ->requiresConfirmation()
                ->modalDescription(__('The Epesi Store deletes your name, e-mail and other registration details and revokes the licence key. This installation keeps working, but without updates and the Store until you register again.'))
                ->action(function (): void {
                    try {
                        app(Registration::class)->delete();
                    } catch (StoreApiException $exception) {
                        Notification::make()->danger()->title(__('Not deleted'))->body($exception->getMessage())->send();

                        return;
                    } catch (ModuleException $exception) {
                        Notification::make()->danger()->title(__('The Epesi Store could not be reached'))->body($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title(__('Registration deleted'))->send();
                    $this->redirect(static::getUrl());
                }),
        ])
            ->label('Store settings')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->button()
            ->color('gray')
            ->visible(fn (): bool => $this->setting()->hasIdentity());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        if (! $this->setting()->isRegistered() || $this->setting()->url_mismatch) {
            return $this->catalog = [];
        }

        try {
            $payload = app(StoreClient::class)->payload();
            $modules = $payload['modules'];
            $this->core = $payload['core'];
            $this->catalogError = null;
        } catch (StoreApiException $exception) {
            // The store's view changed (revoked, moved, purged): take its word for it.
            app(Registration::class)->refreshStatus();
            $this->catalogError = $exception->getMessage();

            return $this->catalog = [];
        } catch (ModuleException $exception) {
            $this->catalogError = $exception->getMessage();

            return $this->catalog = [];
        } catch (Throwable $exception) {
            $this->catalogError = 'The store could not be read: '.$exception->getMessage();

            return $this->catalog = [];
        }

        $installed = Module::query()->pluck('version', 'module_id');
        $core = (string) config('modules.core_version');

        return $this->catalog = array_map(
            fn (array $module): array => $this->row($module, $installed->get($module['id'] ?? ''), $core),
            $modules,
        );
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    protected function row(array $module, ?string $installedVersion, string $core): array
    {
        $compatible = VersionConstraint::satisfies($core, $module['epesi_core'] ?? '*');
        $licensed = (bool) ($module['licensed'] ?? false) && filled($module['download_url'] ?? null);

        $status = match (true) {
            ! $compatible => 'incompatible',
            ! $licensed => 'unlicensed',
            $installedVersion === null => 'available',
            $installedVersion !== ($module['version'] ?? null) => 'update',
            default => 'installed',
        };

        $price = $module['price'] ?? ['type' => 'free'];

        return [
            '__key' => $module['id'],
            'id' => $module['id'],
            'name' => $module['name'] ?? $module['id'],
            'description' => $module['description'] ?? null,
            'category' => $module['category'] ?? null,
            'version' => $module['version'] ?? '?',
            'sha256' => $module['sha256'] ?? '',
            'download_url' => $module['download_url'] ?? null,
            'buy_url' => $module['buy_url'] ?? null,
            'installed_version' => $installedVersion,
            'compatible' => $compatible,
            'status' => $status,
            'status_label' => match ($status) {
                'installed' => 'installed',
                'update' => 'update available',
                'incompatible' => 'needs core '.($module['epesi_core'] ?? '?'),
                'unlicensed' => 'licence required',
                default => 'available',
            },
            'price_label' => ($price['type'] ?? 'free') === 'free'
                ? 'Free'
                : number_format(($price['amount'] ?? 0) / 100, 2).' '.($price['currency'] ?? 'USD'),
        ];
    }
}
