<?php

namespace Epesi\Modules\Appearance\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Administration → Appearance → Logo & Title: the global settings that aren't
 * per-theme or per-user — the title of the main application, and, for the
 * login pages and for the customer portal separately, a title and a logo (one
 * picture for light mode, one for dark). Defaults: "epesi", "epesi",
 * "Customer Portal" and the built-in epesi logo. From here an administrator
 * previews the login pages in both colour modes (PanelLogin's `preview`).
 *
 * Plain Livewire state, not a Filament form: see logo-and-title.blade.php.
 */
class LogoAndTitle extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Logo & Title';

    protected static ?string $title = 'Logo & Title';

    protected static ?string $slug = 'logo-and-title';

    public string $appName = '';

    public string $loginTitle = '';

    public string $portalTitle = '';

    /**
     * Pictures picked and not saved yet, by kind (AppearanceSetting::LOGO_COLUMNS).
     *
     * @var array<string, TemporaryUploadedFile|null>
     */
    public array $logos = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Appearance');
    }

    public function mount(): void
    {
        $this->appName = AppearanceSetting::appName();
        $this->loginTitle = AppearanceSetting::loginTitle();
        $this->portalTitle = AppearanceSetting::portalTitle();
    }

    public function save(): void
    {
        $this->validate([
            'appName' => ['required', 'string', 'max:64'],
            'loginTitle' => ['required', 'string', 'max:64'],
            'portalTitle' => ['required', 'string', 'max:64'],
            'logos.*' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        AppearanceSetting::current()->update([
            'app_name' => $this->appName,
            'login_title' => $this->loginTitle,
            'portal_title' => $this->portalTitle,
        ]);

        foreach (array_filter($this->logos) as $kind => $upload) {
            if (isset(AppearanceSetting::LOGO_COLUMNS[$kind])) {
                $this->replaceLogo($kind, $upload);
            }
        }

        $this->logos = [];

        Notification::make()->success()->title(__('Logo & Title saved'))->send();
    }

    /** The shipped look: the three default titles and the built-in epesi logos. */
    public function resetToDefaults(): void
    {
        $setting = AppearanceSetting::current();
        $disk = AppearanceSetting::logoDisk();

        foreach (AppearanceSetting::LOGO_COLUMNS as $column) {
            if ($path = $setting->{$column}) {
                $disk->delete($path);
            }
        }

        $setting->update([
            'app_name' => null,
            'login_title' => null,
            'portal_title' => null,
            ...array_fill_keys(AppearanceSetting::LOGO_COLUMNS, null),
        ]);

        $this->logos = [];
        $this->resetValidation();
        $this->mount();

        Notification::make()->success()->title(__('Titles and logos reset to the epesi defaults'))->send();
    }

    /** Back to the built-in epesi logo (or the other colour mode's picture). */
    public function removeLogo(string $kind): void
    {
        abort_unless(isset(AppearanceSetting::LOGO_COLUMNS[$kind]), 404);

        $this->replaceLogo($kind, null);
    }

    /**
     * Where the preview frames point: the login page of the main application and
     * of the customer portal, in each colour mode. Only a signed-in administrator
     * is let see them (PanelLogin).
     *
     * @return array<string, array<string, string>>
     */
    public function previewUrls(): array
    {
        $urls = [];

        foreach (['main' => __('Login page'), 'portal' => __('Customer portal login page')] as $panel => $label) {
            foreach (['light', 'dark'] as $mode) {
                $urls[$label][$mode] = Filament::getPanel($panel)->getLoginUrl(['preview' => $mode]);
            }
        }

        return $urls;
    }

    private function replaceLogo(string $kind, ?TemporaryUploadedFile $upload): void
    {
        $column = AppearanceSetting::LOGO_COLUMNS[$kind];
        $setting = AppearanceSetting::current();
        $disk = AppearanceSetting::logoDisk();

        if ($old = $setting->{$column}) {
            $disk->delete($old);
        }

        // A new name every time, so the URL changes and browsers fetch the new picture.
        $path = $upload
            ? $disk->putFileAs('branding', $upload, $kind.'-'.Str::random(12).'.'.$upload->guessExtension())
            : null;

        $setting->update([$column => $path ?: null]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('epesi-appearance::logo-and-title'),
        ]);
    }
}
