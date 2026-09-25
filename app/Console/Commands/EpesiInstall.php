<?php

namespace App\Console\Commands;

use App\Services\Setup\DatabaseSetup;
use App\Services\Setup\Installer;
use App\Services\Setup\InstallOptions;
use App\Services\Setup\Requirements;
use App\Support\Setup\EnvFile;
use App\Support\Setup\SetupCode;
use App\Support\Setup\SetupState;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * The server half of setup — what Epesi's setup.php did before FirstRun:
 * check the server, connect the database, create the tables. The setup
 * wizard can do the same in the browser; this is the command-line way, for
 * servers with a shell.
 *
 * Then either points to the web wizard (/setup) with its one-time code, or —
 * given --admin-email — installs completely here, for scripted and
 * hosting-panel installs.
 */
class EpesiInstall extends Command
{
    protected $signature = 'epesi:install
        {--db-connection= : mysql, mariadb, pgsql or sqlite}
        {--db-host=}
        {--db-port=}
        {--db-database= : database name, or the file path for sqlite}
        {--db-username=}
        {--db-password=}
        {--app-url= : the address users will open, e.g. https://crm.example.com}
        {--admin-name= : with --admin-email and --admin-password, install without the web wizard}
        {--admin-email=}
        {--admin-password=}
        {--profile= : setup type from config/setup.php (default: crm)}
        {--mail=sendmail : sendmail, smtp or log}
        {--demo : also load the demo data}
        {--roundcube : also download and install the Roundcube webmail (GPL-3.0, from roundcube.net)}
        {--dev : keep .env\'s APP_ENV/APP_DEBUG (a development copy) instead of switching to production}
        {--force : continue even when a requirement check fails}';

    protected $description = 'Check the server, configure the database and prepare epesi for setup';

    public function handle(): int
    {
        if (! $this->checkRequirements() && ! $this->option('force')) {
            $this->error('Fix the problems above and run this again (or pass --force).');

            return self::FAILURE;
        }

        $this->prepareEnvironmentFile();

        if (! $this->configureDatabase()) {
            return self::FAILURE;
        }

        $this->components->task('Creating the database tables', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);

        if (SetupState::isInstalled()) {
            $this->components->info('epesi is already set up — the database has users. Nothing else to do.');

            return self::SUCCESS;
        }

        if ($this->option('admin-email')) {
            return $this->installHeadless();
        }

        $this->components->info('The server is ready. Finish setup in your browser: '.rtrim((string) config('app.url'), '/').'/setup');
        $this->components->twoColumnDetail('Setup code (the wizard asks for it first)', '<fg=yellow;options=bold>'.SetupCode::current().'</>');

        return self::SUCCESS;
    }

    protected function checkRequirements(): bool
    {
        $rows = app(Requirements::class)->check();

        $this->table(['Requirement', 'Status'], array_map(fn (array $row): array => [$row['label'], $row['status']], $rows));

        return Requirements::passes($rows);
    }

    protected function prepareEnvironmentFile(): void
    {
        $env = app()->environmentFilePath();

        if (! is_file($env) && is_file(base_path('.env.example'))) {
            copy(base_path('.env.example'), $env);
            $this->components->info('Created .env from .env.example.');
        }

        if (blank(config('app.key'))) {
            $this->callSilently('key:generate', ['--force' => true]);
            $this->components->info('Generated the application key.');
        }

        if ($url = $this->option('app-url')) {
            (new EnvFile)->set(['APP_URL' => $url]);
            config(['app.url' => $url]);
        }
    }

    /**
     * Epesi's setup.php database page. Values come from the options, else
     * from a prompt (when interactive) prefilled with the current settings.
     */
    protected function configureDatabase(): bool
    {
        $current = config('database.default');
        $connection = $this->option('db-connection') ?? ($this->input->isInteractive()
            ? select('Database type', ['mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'], default: $current === 'sqlite' ? 'mysql' : $current)
            : $current);

        $database = app(DatabaseSetup::class);

        if ($database->isSqlite($connection)) {
            $input = ['database' => $this->value('db-database', 'Database file', database_path('database.sqlite'), config('database.connections.sqlite.database'))];
        } else {
            $defaults = config("database.connections.{$connection}", []);
            $input = [
                'host' => $this->value('db-host', 'Database server', $defaults['host'] ?? '127.0.0.1'),
                'port' => $this->value('db-port', 'Port', (string) ($defaults['port'] ?? '')),
                'database' => $this->value('db-database', 'Database name', $defaults['database'] ?? 'epesi'),
                'username' => $this->value('db-username', 'Database user', $defaults['username'] ?? 'root'),
                'password' => $this->option('db-password') ?? ($this->input->isInteractive()
                    ? password('Database password')
                    : ($defaults['password'] ?? '')),
            ];
        }

        $settings = $database->settings($connection, $input);

        try {
            $database->connect($settings);
        } catch (Throwable $e) {
            $this->error('Could not connect to the database: '.$e->getMessage());

            return false;
        }

        $database->save($settings);
        $this->components->info('Connected to the database and saved the settings in .env.');

        return true;
    }

    protected function value(string $option, string $label, ?string $default, ?string $current = null): string
    {
        if ($this->option($option) !== null) {
            return (string) $this->option($option);
        }

        return $this->input->isInteractive()
            ? text($label, default: (string) ($current ?? $default))
            : (string) ($current ?? $default);
    }

    protected function installHeadless(): int
    {
        foreach (['admin-name', 'admin-password'] as $required) {
            if (blank($this->option($required))) {
                $this->error("--{$required} is needed with --admin-email.");

                return self::FAILURE;
            }
        }

        $installer = app(Installer::class);

        try {
            $installer->install(new InstallOptions(
                profile: $this->option('profile') ?: (string) config('setup.default_profile'),
                adminName: (string) $this->option('admin-name'),
                adminEmail: (string) $this->option('admin-email'),
                adminPassword: (string) $this->option('admin-password'),
                mailMethod: (string) $this->option('mail'),
                demoData: (bool) $this->option('demo'),
                roundcube: (bool) $this->option('roundcube'),
                development: (bool) $this->option('dev'),
            ));
        } catch (Throwable $e) {
            $this->error('Setup did not finish: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($installer->warnings() as $warning) {
            $this->warn($warning);
        }

        $this->components->info('epesi is installed. Sign in as '.$this->option('admin-email').' to finish the module settings.');

        return self::SUCCESS;
    }
}
