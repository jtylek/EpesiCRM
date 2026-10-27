<?php

namespace App\Filament\Administration\Resources\Modules\Pages;

use App\Filament\Administration\Resources\Modules\ModuleResource;
use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Services\Modules\ModuleDiscovery;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use App\Support\Demo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Administration → Modules → Discover modules: every module directory found
 * under modules/, registered or not, so an administrator can see what is on
 * disk and register a module that was unpacked by hand, is a developer's
 * working copy, or lost its `modules` row (an uninstall that kept its files).
 */
class DiscoverModules extends Page implements HasTable
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string $resource = ModuleResource::class;

    protected static ?string $title = 'Discover modules';

    protected static ?string $breadcrumb = 'Discover';

    protected string $view = 'filament.administration.discover-modules';

    public function getBreadcrumb(): string
    {
        return __((string) static::$breadcrumb);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect(app(ModuleDiscovery::class)->scan())
                ->map(fn (array $module): array => ['__key' => $module['path'], ...$module]))
            ->columns([
                TextColumn::make('name')->label(__('Name')),
                TextColumn::make('id')->label(__('Id'))->placeholder('-'),
                TextColumn::make('version')->label(__('Version'))->placeholder('-'),
                TextColumn::make('path')->label(__('Directory'))->formatStateUsing(fn (string $state): string => "modules/{$state}"),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ModuleDiscovery::REGISTERED => __('Registered'),
                        ModuleDiscovery::AVAILABLE => __('Not registered'),
                        ModuleDiscovery::BLOCKED => __('Cannot be registered'),
                        default => __('Invalid'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        ModuleDiscovery::REGISTERED => 'success',
                        ModuleDiscovery::AVAILABLE => 'info',
                        ModuleDiscovery::BLOCKED => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('problem')->label(__('Problem'))->placeholder('-')->wrap(),
            ])
            // The panel's row-click-opens-View hook expects a module row; these are directories.
            ->recordUrl(null)
            ->recordActions([$this->registerAction()], position: RecordActionsPosition::AfterColumns)
            ->toolbarActions([])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to modules')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(ModuleResource::getUrl()),
        ];
    }

    protected function registerAction(): Action
    {
        return Demo::guard(Action::make('register')
            ->label('Register')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('success')
            ->visible(fn (array $record): bool => $record['status'] === ModuleDiscovery::AVAILABLE)
            ->requiresConfirmation()
            ->modalHeading(fn (array $record): string => __('Register :module?', ['module' => $record['name']]))
            ->modalDescription(__('Runs its migrations and enables it. A module runs with the same privileges as the application itself: register only code you trust.'))
            ->action(function (array $record): void {
                // The list is re-read here: the record came from the browser.
                $module = collect(app(ModuleDiscovery::class)->scan())->firstWhere('path', $record['path'] ?? null);

                if (($module['status'] ?? null) !== ModuleDiscovery::AVAILABLE) {
                    Notification::make()->danger()->title(__('This module cannot be registered'))->body($module['problem'] ?? null)->send();

                    return;
                }

                try {
                    $registered = app(ModuleInstaller::class)->registerExisting($module['path']);
                } catch (ModuleException $exception) {
                    Notification::make()->danger()->title(__('Module not registered'))->body($exception->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Registered :module :version', ['module' => $registered->name, 'version' => $registered->version]))
                    ->body(__('Reload the app to see its screens.'))
                    ->send();
            }));
    }
}
