<?php

namespace Epesi\Console\Develop;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Implements the upgrade half of AI-private/release-packaging-plan.md: apply a manually
 * downloaded SourceForge release zip (built by `dev:dist:create`) over an already-installed
 * copy of EPESI, replacing Core files and running DB patches - the manual-zip equivalent of
 * what update.php's EpesiPackageDownloader/EpesiUpdatePackage already do for the
 * ess.epe.si network-update channel.
 */
class UpdateApplyPackageCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('update:apply-package')
            ->setDescription('Apply a manually downloaded release zip over this install (Core files + DB patches)')
            ->addArgument(
                'zip',
                InputArgument::REQUIRED,
                'Path to the new release zip (built by dev:dist:create)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Apply even though this looks like a git-deployed checkout (.git or .noupdate present)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $st = new SymfonyStyle($input, $output);
        $zip_path = $input->getArgument('zip');

        if (!is_file($zip_path)) {
            $st->error("No such file: $zip_path");
            return Command::FAILURE;
        }

        // Same check as update.php's EpesiUpdate::net_update_blocked(), reimplemented
        // rather than reused - update.php is a standalone entrypoint with its own
        // require_once('include.php') and $_GET-driven flow, not a library. A git checkout
        // must be updated via `git pull`; extracting a zip over it can silently destroy
        // uncommitted work, and unlike the network updater this command is also more
        // destructive (wholesale vendor/ delete plus manifest-diff deletes), so the default
        // must refuse outright, not just warn.
        $git_blocked = file_exists('.git') || file_exists('.noupdate');
        if ($git_blocked && !$input->getOption('force')) {
            $st->error('Refusing to run: this looks like a git-deployed checkout (.git or '
                . '.noupdate present). Use "git pull" to update it instead, or pass --force '
                . 'if you really want a zip upgrade applied here anyway.');
            return Command::FAILURE;
        }
        if ($git_blocked) {
            $st->warning('--force given: applying a zip upgrade over what looks like a git-deployed checkout.');
        }

        require_once 'include/backups.php';
        try {
            $package = new \BackupArchive($zip_path);
        } catch (\ErrorException $e) {
            $st->error("Could not open $zip_path: " . $e->getMessage());
            return Command::FAILURE;
        }
        $new_manifest = $package->list_files();
        if (!$new_manifest) {
            $st->error("Package appears empty or unreadable: $zip_path");
            return Command::FAILURE;
        }

        // The manifest of what the CURRENTLY installed release shipped, written by that
        // release's own `dev:dist:create` run and extracted onto disk when it was applied.
        // Absent on an install that predates this mechanism (or a fresh install built
        // before this feature existed) - cleanup is then skipped, not guessed at.
        $old_manifest_file = 'update/MANIFEST.txt';
        $old_manifest = null;
        if (is_file($old_manifest_file)) {
            $old_manifest = array_filter(preg_split('/\r?\n/', file_get_contents($old_manifest_file)), 'strlen');
        } else {
            $st->warning("No $old_manifest_file found on this install - skipping leftover-file "
                . 'cleanup (nothing to diff the new package against). Expected for an install '
                . 'that predates this mechanism.');
        }

        $confirm_msg = "This will delete vendor/ entirely and extract \"$zip_path\" over this installation"
            . ($old_manifest ? ', then remove files the previous release shipped that this one no longer does' : '')
            . '. Make sure you have a recent file and database backup. Continue?';
        if (!$st->confirm($confirm_msg, false)) {
            $st->writeln('Aborted.');
            return Command::FAILURE;
        }

        $st->writeln('Turning on maintenance mode...');
        \MaintenanceMode::turn_on(__('%s is currently updating. Please wait or contact your system administrator.', array(EPESI)));

        try {
            if (is_dir('vendor')) {
                $st->writeln('Removing vendor/...');
                // vendor/ is 100% composer-generated build output (never hand-edited) whose
                // internal structure can restructure significantly between releases -
                // wholesale delete-then-reextract is simpler and more robust than diffing it
                // path-by-path, mirroring what the network updater's wipe()-then-extract()
                // already achieves implicitly for vendor/.
                \recursive_rmdir('vendor');
            }

            $st->writeln("Extracting $zip_path...");
            if (!$package->extractTo('.')) {
                throw new \ErrorException("Failed to extract $zip_path");
            }

            if ($old_manifest) {
                $st->writeln('Removing files the previous release no longer ships...');
                $removed = self::cleanup_removed_paths($old_manifest, $new_manifest);
                foreach ($removed as $path) {
                    $st->writeln("  removed: $path");
                }
            }

            $st->writeln('Applying patches...');
            \PatchUtil::apply_new(true);

            \Base_ThemeCommon::themeup();
            \ModuleManager::create_load_priority_array();
            \Variable::set('version', EPESI_VERSION);
        } finally {
            \MaintenanceMode::turn_off();
        }

        $st->success("Updated to $zip_path.");
        return Command::SUCCESS;
    }

    /**
     * Paths the old release shipped that the new one no longer does, safe to delete because
     * both manifests were built the same way (dev:dist:create's own exclude list) - so this
     * diff can never legitimately name a data/, modules/Premium/ or non-Tutorial
     * modules/Custom/* path. $protected below is defense in depth regardless, same
     * belt-and-braces spirit as update.php's orphaned_modules_gate(): never let a
     * manifest-diff delete touch install-owned data or per-install/premium modules, even if
     * a future manifest is built incorrectly.
     *
     * @return string[] paths actually deleted
     */
    private static function cleanup_removed_paths(array $old_manifest, array $new_manifest): array
    {
        $removed = array_diff($old_manifest, $new_manifest);
        $protected = '#^(data/|modules/Premium(/|$)|modules/Custom/(?!Tutorial(/|$)))#';
        $deleted = array();
        foreach ($removed as $path) {
            $path = trim($path);
            if ($path === '' || preg_match($protected, $path)) {
                continue;
            }
            $full = './' . $path;
            if (is_file($full)) {
                unlink($full);
                $deleted[] = $path;
            } elseif (is_dir($full)) {
                // Only removes if empty - never force-delete a leftover directory that still
                // holds something (e.g. install-added content the old release's manifest
                // happened to also list as its own directory entry).
                @rmdir($full);
            }
        }
        return $deleted;
    }
}
