<?php

namespace Epesi\Modules\Roundcube\Console;

use Epesi\Modules\Roundcube\Roundcube;
use Epesi\Modules\Roundcube\Services\RoundcubeInstaller;
use Illuminate\Console\Command;
use Throwable;

/**
 * Installs or upgrades the Roundcube webmail behind the Mailbox page. Installing
 * the module can't do it: a module install only runs migrations.
 */
class InstallRoundcubeCommand extends Command
{
    protected $signature = 'roundcube:install
        {--release= : Another Roundcube version than the one pinned in config/epesi-roundcube.php}
        {--sha256= : The checksum of that version\'s -complete.tar.gz, from roundcube.net}
        {--configure-only : Only rewrite Roundcube\'s config and update its tables}';

    protected $description = 'Download, install or upgrade the Roundcube webmail for the Mailbox page';

    public function handle(RoundcubeInstaller $installer): int
    {
        $progress = fn (string $line) => $this->line($line);

        try {
            if ($this->option('configure-only')) {
                $installer->configure($progress);
            } else {
                $version = $this->option('release') ?: (string) config('epesi-roundcube.release.version');
                $sha256 = $this->option('sha256') ?: ($this->option('release') ? null : (string) config('epesi-roundcube.release.sha256'));

                if (! $sha256) {
                    $this->error('--release needs --sha256: the checksum published on https://roundcube.net/download/.');

                    return self::FAILURE;
                }

                $installer->install($version, $sha256, $progress);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Roundcube is ready at '.Roundcube::url());
        $this->line('The web server must serve that directory as plain files, including '
            .'".../roundcube/?_task=mail" and ".../roundcube/static.php/..." URLs; see AI-shared/Epesi-Laravel-Roundcube.md.');

        return self::SUCCESS;
    }
}
