<?php

namespace App\Filament\Administration\Resources\Modules;

use App\Models\Module;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * The actions behind the Modules screen, shared by the table rows and the View
 * page header so both offer the same operations.
 */
class ModuleActions
{
    public static function install(): Action
    {
        return Action::make('install')
            ->label('Install module')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (): bool => (bool) config('modules.install_enabled'))
            ->modalDescription(__('An installed module runs with the same privileges as the application itself. Only install packages you trust.'))
            ->schema([
                FileUpload::make('package')
                    ->label('Module package (.zip)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                    ->maxSize((int) (config('modules.zip.max_size') / 1024))
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                // Also checked in visible() above; repeated here because this is
                // the boundary where an upload turns into executable code.
                if (! config('modules.install_enabled')) {
                    Notification::make()->danger()->title(__('Module installation is disabled'))->send();

                    return;
                }

                $upload = is_array($data['package']) ? reset($data['package']) : $data['package'];

                if (! $upload instanceof TemporaryUploadedFile) {
                    Notification::make()->danger()->title(__('Upload failed'))->send();

                    return;
                }

                try {
                    $module = app(ModuleInstaller::class)->installFromZip($upload->getRealPath());
                } catch (ModuleException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('Module not installed'))
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Installed :module :version', ['module' => $module->name, 'version' => $module->version]))
                    ->body(__('Reload the app to see its screens.'))
                    ->send();
            });
    }

    public static function toggle(): Action
    {
        return Action::make('toggle')
            ->label(fn (Module $record): string => $record->enabled ? __('Disable') : __('Enable'))
            ->icon(fn (Module $record): Heroicon => $record->enabled ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
            ->color(fn (Module $record): string => $record->enabled ? 'warning' : 'success')
            // A core module (the RecordBrowser engine) is what core screens are
            // built on — the installer refuses anyway, but offering a button
            // that can only fail is worse than not offering one.
            ->visible(fn (Module $record): bool => ! $record->isCore())
            ->requiresConfirmation()
            ->modalDescription(fn (Module $record): string => $record->enabled
                ? 'Its screens disappear until it is enabled again. Its data is left untouched.'
                : 'Its screens become available again on the next page load.')
            ->action(function (Module $record): void {
                $installer = app(ModuleInstaller::class);

                try {
                    $record->enabled ? $installer->disable($record) : $installer->enable($record);
                } catch (ModuleException $exception) {
                    Notification::make()->danger()->title(__('Could not change state'))->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title($record->module_id.' is now '.($record->enabled ? 'enabled' : 'disabled'))
                    ->send();
            });
    }

    /**
     * Filament panels are built during service-provider registration, so a
     * module installed in *this* request isn't in the panel yet — permissions
     * can only be generated on a later request, which is why this is an
     * explicit action rather than a step inside the installer.
     *
     * `--option=permissions`, because Shield's policy generation overwrites
     * every existing policy with a `can('View:X')` stub — including the
     * modules' hand-written ownership policies. And the command switches
     * Filament's current panel to the one it generates for, which then
     * renders this Administration page's links against the wrong panel.
     */
    public static function generatePermissions(): Action
    {
        return Action::make('generatePermissions')
            ->label('Generate permissions')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn (Module $record): bool => $record->enabled && filled($record->plugin_class))
            ->requiresConfirmation()
            ->modalDescription(__('Creates the Shield permissions for this module\'s screens so roles other than super_admin can be granted access.'))
            ->action(function (Module $record): void {
                $currentPanel = Filament::getCurrentPanel();

                try {
                    foreach ($record->panels ?? [] as $panel) {
                        Artisan::call('shield:generate', ['--all' => true, '--panel' => $panel, '--option' => 'permissions', '--no-interaction' => true]);
                    }
                } catch (Throwable $exception) {
                    Notification::make()->danger()->title(__('Permission generation failed'))->body($exception->getMessage())->send();

                    return;
                } finally {
                    Filament::setCurrentPanel($currentPanel);
                }

                Notification::make()->success()->title(__('Permissions generated'))->send();
            });
    }

    public static function uninstall(): Action
    {
        return Action::make('uninstall')
            ->label('Uninstall')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Module $record): bool => ! $record->isCore())
            ->requiresConfirmation()
            ->modalHeading(fn (Module $record): string => __('Uninstall :module?', ['module' => $record->name]))
            ->modalDescription(fn (Module $record): string => "This deletes modules/{$record->path}. Any database tables the module created keep their data — reinstalling reuses them.")
            ->action(function (Module $record): void {
                try {
                    app(ModuleInstaller::class)->uninstall($record);
                } catch (ModuleException $exception) {
                    Notification::make()->danger()->title(__('Could not uninstall'))->body($exception->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title(__('Uninstalled :module', ['module' => $record->module_id]))->send();
            });
    }
}
