<?php

namespace Epesi\Modules\Store\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Module;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use App\Support\Modules\VersionConstraint;
use BackedEnum;
use Epesi\Modules\Store\Models\StoreSetting;
use Epesi\Modules\Store\Services\StoreClient;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

/**
 * Browse a store server's catalog and install or update from it.
 *
 * The catalog is advisory only: what it says decides which rows are *offered*,
 * while the package itself is checksum-verified on download and then put through
 * the core's own ModuleArchive validation by ModuleInstaller. A compromised
 * store can therefore mislead this screen, but it cannot get an archive past the
 * installer that a hand-uploaded one wouldn't also have to pass.
 */
class Store extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected string $view = 'epesi-store::store';

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $catalog = null;

    protected ?string $catalogError = null;

    public function getTitle(): string
    {
        return __('Store');
    }

    public static function getNavigationLabel(): string
    {
        return __('Store');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->settingsAction(),
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->catalog = null),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect($this->records()))
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (array $record): ?string => $record['description'] ?? null),
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
            ])
            // Closures, not values: table() is configured before records() has
            // run, so a fetch error isn't known yet at this point.
            ->emptyStateHeading(fn (): string => $this->catalogError ?? 'Nothing in the catalog')
            ->emptyStateDescription(fn (): string => $this->catalogError
                ? 'Check the catalog URL in Store settings.'
                : 'The store has no modules published yet.')
            ->paginated(false);
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

    protected function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Store settings')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->fillForm(fn (): array => StoreSetting::current()->only(['catalog_url', 'licence_key']))
            ->schema([
                TextInput::make('catalog_url')
                    ->label('Catalog URL')
                    ->helperText(__('Base URL of the store API, e.g. https://store.epesi.com/store-api'))
                    ->url()
                    ->required(),
                TextInput::make('licence_key')
                    ->label('Licence key')
                    ->helperText(__('Optional. Required only for paid modules.')),
            ])
            ->action(function (array $data): void {
                StoreSetting::current()->update([
                    'catalog_url' => $data['catalog_url'],
                    'licence_key' => $data['licence_key'] ?: null,
                ]);

                $this->catalog = null;

                Notification::make()->success()->title(__('Store settings saved'))->send();
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function records(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        try {
            $modules = app(StoreClient::class)->catalog();
            $this->catalogError = null;
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
