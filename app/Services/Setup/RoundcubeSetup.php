<?php

namespace App\Services\Setup;

use App\Models\Module;
use App\Services\Modules\ModuleInstaller;
use App\Support\Modules\ModuleManifest;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Adds the Roundcube webmail (the Mailbox page) in one step, for someone who
 * has never seen a command line: turns on the Epesi/Roundcube module and
 * whatever it requires, then downloads and sets up Roundcube itself.
 *
 * Roundcube is a separate project under the GPL, so it never ships with
 * epesi: it is downloaded only once someone has said yes to it — in the setup
 * wizard, on the Mailbox page, or under Administration → Modules. All three
 * show notice() first. `php artisan roundcube:install` is the same download
 * for the command line.
 */
class RoundcubeSetup
{
    public const MODULE_PATH = 'Epesi/Roundcube';

    public const MODULE_ID = 'epesi/roundcube';

    public const LICENSE_URL = 'https://www.gnu.org/licenses/gpl-3.0.html';

    public const PROJECT_URL = 'https://roundcube.net';

    /** The module's installer; named, not imported, since the module may not be loaded. */
    protected const INSTALLER = 'Epesi\\Modules\\Roundcube\\Services\\RoundcubeInstaller';

    public function __construct(
        protected ModuleInstaller $modules,
        protected ModulePlan $plan,
    ) {}

    /** Whether the module is in this copy of epesi at all. */
    public function available(): bool
    {
        return is_file(Module::directoryFor(self::MODULE_PATH).DIRECTORY_SEPARATOR.'module.json');
    }

    /** The module is on and Roundcube itself has been downloaded. */
    public function installed(): bool
    {
        return Module::query()->where('module_id', self::MODULE_ID)->where('enabled', true)->exists()
            && is_file(rtrim((string) config('epesi-roundcube.public_path', public_path('roundcube')), '\\/').DIRECTORY_SEPARATOR.'index.php');
    }

    /**
     * The module and Roundcube, start to finish. Safe to run again: done
     * steps are skipped, and the download replaces an earlier one.
     */
    public function install(): void
    {
        $this->enableModule();
        $this->download();
    }

    /**
     * Turns on the module and what it requires (Mail) — without Roundcube
     * itself, the Mailbox page offers the download.
     */
    public function enableModule(): void
    {
        if (! $this->available()) {
            throw new RuntimeException('The Roundcube module is missing from modules/'.self::MODULE_PATH.'.');
        }

        $manifests = $this->plan->for([self::MODULE_PATH]);

        foreach ($manifests as $manifest) {
            /** @var ModuleManifest $manifest */
            $existing = Module::query()->where('module_id', $manifest->id)->first();

            if ($existing === null) {
                $this->modules->registerExisting($manifest->path);
            } elseif (! $existing->enabled) {
                $this->modules->enable($existing);
            }
        }

        ModuleLoader::load($manifests);
    }

    /**
     * Downloads the release pinned in the module's config, checks it, and
     * sets it up — see RoundcubeInstaller. The module must be loaded.
     */
    public function download(): void
    {
        // Downloading, unpacking a few thousand files and creating tables
        // outlasts PHP's usual 30 seconds (XAMPP: 120).
        @set_time_limit(0);

        $installer = app(self::INSTALLER);

        $installer->install(
            (string) config('epesi-roundcube.release.version'),
            (string) config('epesi-roundcube.release.sha256'),
            function (string $line): void {},
        );
    }

    /**
     * What someone is told before saying yes: what Roundcube is, that it is
     * someone else's GPL software, and that it comes from the internet.
     */
    public static function notice(): Htmlable
    {
        return new HtmlString(
            '<p>'.e(__('Roundcube is a free webmail program. With it, you read and send the e-mail of your own mailbox on a Mailbox page inside epesi, and file messages into the CRM with one click.')).'</p>'
            .'<p style="margin-top:.5rem">'.__('Roundcube is made by a separate open-source project and is licensed under the :license. It is not part of epesi, so it is downloaded from the :project (about 7 MB) only if you choose to install it.', [
                'license' => '<a href="'.self::LICENSE_URL.'" target="_blank" rel="noopener" style="text-decoration:underline">'.e(__('GNU General Public License, version 3')).'</a>',
                'project' => '<a href="'.self::PROJECT_URL.'" target="_blank" rel="noopener" style="text-decoration:underline">'.e(__('Roundcube project')).'</a>',
            ]).'</p>',
        );
    }
}
