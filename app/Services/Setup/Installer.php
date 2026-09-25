<?php

namespace App\Services\Setup;

use App\Models\Module;
use App\Models\User;
use App\Services\Modules\ModuleInstaller;
use App\Support\Modules\ModuleManifest;
use App\Support\Setup\EnvFile;
use App\Support\Setup\SetupCode;
use App\Support\Setup\SetupState;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Installs the system from the setup wizard's answers — FirstRun::done():
 * roles, the chosen modules, the administrator, the mail settings, and (new
 * here) optional demo data.
 *
 * Safe to run again after a failure part-way: modules already registered are
 * skipped and nothing is marked installed until the administrator exists. The
 * administrator is created last because an existing user is what makes the
 * system count as installed (SetupState), which closes the wizard.
 */
class Installer
{
    /** @var array<int, string> */
    protected array $warnings = [];

    public function __construct(
        protected ModuleInstaller $modules,
        protected ModulePlan $plan,
    ) {}

    public function install(InstallOptions $options): User
    {
        $this->warnings = [];

        // Two browsers submitting the last page at once must not create two
        // administrators.
        $lock = Cache::lock('epesi-setup', 600);

        if (! $lock->get()) {
            throw new SetupException('Setup is already running in another window.');
        }

        try {
            SetupState::flush();

            if (SetupState::isInstalled()) {
                throw new SetupException('This system is already set up.');
            }

            $profile = config("setup.profiles.{$options->profile}");

            if (! is_array($profile)) {
                throw new SetupException("Unknown setup type \"{$options->profile}\".");
            }

            @set_time_limit(0);

            (new RoleSeeder)->run();

            $modules = $profile['modules'] ?? [];

            if ($options->roundcube) {
                $modules[] = RoundcubeSetup::MODULE_PATH;
            }

            $installed = $this->installModules($modules);

            $admin = DB::transaction(function () use ($options): User {
                $admin = User::create([
                    'name' => $options->adminName,
                    'email' => $options->adminEmail,
                    'password' => $options->adminPassword,
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
                $admin->assignRole('super_admin');

                if ($options->demoData) {
                    app(DemoDataSeeder::class)->run($admin);
                }

                return $admin;
            });

            $this->configureMail($options);
            $this->configureEnvironment($options);

            SetupState::writeMarker([
                'installed_at' => now()->toIso8601String(),
                'profile' => $options->profile,
                'modules' => $installed,
                'finish_pending' => true,
            ]);

            // Installed: the one-time code has done its job.
            SetupCode::forget();

            if ($options->roundcube) {
                $this->downloadRoundcube();
            }

            // Shield's permission list is built from the panels, which were
            // assembled at the start of this request — before the modules
            // above existed. The next request sees them; generating now would
            // miss every one. Left to Administration → Modules, as for any
            // module installed from the GUI.

            return $admin;
        } finally {
            $lock->release();
        }
    }

    /**
     * Last, once everything else is saved: it needs the internet and takes
     * a while, and setup is complete without it. A failure is a warning —
     * the Mailbox page offers the download again.
     */
    protected function downloadRoundcube(): void
    {
        try {
            app(RoundcubeSetup::class)->download();
        } catch (Throwable $e) {
            report($e);
            $this->warnings[] = __('Roundcube could not be downloaded (:reason). Everything else is installed. To try again, open Mailbox in the menu and click "Download and install Roundcube".', ['reason' => $e->getMessage()]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string> ids of the modules now installed
     */
    protected function installModules(array $paths): array
    {
        $ids = [];
        $manifests = $this->plan->for($paths);

        foreach ($manifests as $manifest) {
            /** @var ModuleManifest $manifest */
            $existing = Module::query()->where('module_id', $manifest->id)->first();

            if ($existing === null) {
                $this->modules->registerExisting($manifest->path);
            } elseif (! $existing->enabled) {
                $this->modules->enable($existing);
            }

            $ids[] = $manifest->id;
        }

        // The administrator and the demo records are created right after,
        // and saving a record needs the modules' providers — the CRM morph
        // aliases, the Watchdog listener.
        ModuleLoader::load($manifests);

        return $ids;
    }

    /**
     * FirstRun's "Mail settings" page. Epesi kept these in its variables
     * table; here they are the application mailer's own .env keys. When .env
     * can't be written (a locked-down host) setup still finishes, and the
     * warning says what to set by hand.
     */
    /**
     * An installed system runs in production mode: .env.example's
     * APP_DEBUG=true would show configuration values, the database password
     * among them, on every error page. `epesi:install --dev` keeps a
     * developer's settings.
     */
    protected function configureEnvironment(InstallOptions $options): void
    {
        if ($options->development) {
            return;
        }

        $values = ['APP_ENV' => 'production', 'APP_DEBUG' => 'false'];
        $env = new EnvFile;

        if (! $env->writable()) {
            $this->warnings[] = __('.env is not writable, so epesi is still in development mode. Set APP_ENV=production and APP_DEBUG=false in .env yourself: with debug on, error pages show configuration values.');

            return;
        }

        $env->set($values);
    }

    protected function configureMail(InstallOptions $options): void
    {
        $values = match ($options->mailMethod) {
            InstallOptions::MAIL_SMTP => [
                'MAIL_MAILER' => 'smtp',
                'MAIL_SCHEME' => $options->smtpSecurity === 'ssl' ? 'smtps' : 'smtp',
                'MAIL_HOST' => $options->smtpHost,
                'MAIL_PORT' => $options->smtpPort ?: ($options->smtpSecurity === 'ssl' ? 465 : 587),
                'MAIL_USERNAME' => $options->smtpUsername,
                'MAIL_PASSWORD' => $options->smtpPassword,
            ],
            InstallOptions::MAIL_LOG => ['MAIL_MAILER' => 'log'],
            default => ['MAIL_MAILER' => 'sendmail'],
        };

        // FirstRun sent system mail from the administrator's address.
        $values += [
            'MAIL_FROM_ADDRESS' => $options->adminEmail,
            'MAIL_FROM_NAME' => config('app.name'),
        ];

        $env = new EnvFile;

        if (! $env->writable()) {
            $this->warnings[] = 'The mail settings could not be saved because .env is not writable. Set these in .env yourself: '
                .collect($values)->map(fn ($v, $k): string => $k.'='.($k === 'MAIL_PASSWORD' ? '…' : $v))->implode(', ');

            return;
        }

        $env->set($values);

        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
            // A config cache that can't be cleared only delays the change.
        }
    }
}
