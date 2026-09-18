<?php

namespace Epesi\Console\Develop;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateDistCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('dev:dist:create')
            ->setDescription('Create a distributable EPESI package (zip) with an empty data/ directory')
            ->addArgument(
                'file',
                InputArgument::OPTIONAL,
                'Full path to the output zip file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $st = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        if (!$file) {
            $default = dirname(getcwd()) . DIRECTORY_SEPARATOR . 'epesi-' . EPESI_VERSION . '-r' . EPESI_REVISION . '.zip';
            $file = $st->ask('Full path to output zip file (missing directories will be created)', $default);
        }
        if (!preg_match('/\.zip$/i', $file)) {
            $file .= '.zip';
        }

        if (file_exists($file) && !$st->confirm("File \"$file\" already exists. Overwrite?", false)) {
            $st->writeln('Aborted.');
            return Command::FAILURE;
        }

        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            $st->error("Could not create directory: $dir");
            return Command::FAILURE;
        }

        require_once 'include/backups.php';

        // RecursiveDirectoryIterator returns backslash-separated paths on Windows,
        // so the exclude regexes must match either separator, not just "/".
        // $sep is a complete alternative ("...(sep|$)"); $sep_chars is just the
        // characters, for embedding inside a [^...] class - nesting $sep's own
        // brackets inside another [^...] produces a malformed, silently-never-
        // matching regex.
        $sep = '[\\\\/]';
        $sep_chars = '\\\\/';
        $exclude = array(
            '^\.git(' . $sep . '|$)',
            '^\.claude(' . $sep . '|$)',
            '^\.github(' . $sep . '|$)',
            '^\.history(' . $sep . '|$)',
            '^\.playwright-mcp(' . $sep . '|$)', // gitignored Playwright MCP run artifacts (screenshots/logs)
            '^data' . $sep . '.+', // keep the data/ directory entry itself, drop everything inside it
            '^temp(' . $sep . '|$)', // Smarty compile/cache/config output (see TEMP_DIR) - regenerated on first request
            '^[^' . $sep_chars . ']+\.zip$', // any leftover distribution/test zip sitting at the project root
            '^(?!README\.md$)[^' . $sep_chars . ']+\.md$', // root-level docs other than README.md
            // root-level dev/CI tooling that has no business in a runtime distribution.
            // NOTE: htaccess.txt is intentionally NOT excluded - setup.php's own
            // check_htaccess() copies it to build data/.htaccess during install
            // (copy('htaccess.txt','data/.htaccess')), so removing it would break
            // that step. .htaccess itself (this dev instance's own, already-tuned
            // copy) IS excluded, since setup.php generates the real one fresh.
            '^(\.htaccess|\.gitattributes|\.gitignore|\.mcp\.json|debug\.php|PEAR\.php|phpstan.*|playbook\.yml|rector.*)$',
            // Static-analysis tooling (tools/composer.json + tools/vendor/, see that
            // file). Same reasoning as phpstan.*/rector.* above - the configs were
            // already excluded, so leaving the tools they configure in the package
            // would have been inconsistent. Excluded whether or not tools/vendor/ is
            // committed, so the gitignore decision can be revisited without this
            // needing to change.
            '^tools(' . $sep . '|$)',
            // Internal development notes - the living notes for developers/AI assistants
            // working ON Epesi, not documentation for people running it. The root-level
            // "*.md except README.md" rule above only matches root level, so this whole
            // folder (38 files, ~700 KB) was shipping in every release package.
            //
            // This matters beyond size: this command archives the WORKING TREE, not
            // `git ls-files`, so a gitignored file that happens to exist locally still
            // ships. Some gitignored notes hold a developer's own hosting/account details
            // - without this rule, building a release on that machine would put them in
            // the zip.
            //
            // Nothing at runtime reads this folder (every reference in modules/ is a
            // source comment), so excluding it cannot affect a running install. README.md
            // does link into it; those links resolve on GitHub, which is where the README
            // is actually read, and the package is not where anyone goes for the module
            // tutorial - `git clone` is.
            '^AI-shared(' . $sep . '|$)',
            // AI-private/ is a separate nested git repo, gitignored from this one, that
            // exists only on a core developer's own checkout - confidential deployment/
            // hosting notes, plans, and archived docs never meant for an end user. Same
            // "working tree, not git ls-files" reasoning as AI-shared/ above: if it's
            // physically present when this command runs, nothing else would stop it
            // from shipping.
            '^AI-private(' . $sep . '|$)',
            // Premium/Custom modules are per-install add-ons, not part of Core - each is
            // meant to be its own nested git repo, gitignored from this one (see
            // .gitignore). They only reach this exclude list at all because, again, this
            // command walks the WORKING TREE, not `git ls-files`: on a core developer's
            // machine they're gitignored but physically present on disk, so without an
            // explicit rule here they'd ship straight into the public SourceForge zip
            // (see AI-private/Sourceforge-distribution.md for the build checklist this
            // closes a manual step of).
            '^modules' . $sep . 'Premium(' . $sep . '|$)',
            // modules/Custom/* is the same idea, except Tutorial - the one example
            // module that ships with this repo (see AI-shared/Dev-Tutorial.md) and is
            // meant to travel with every distribution. Negative lookahead so only
            // Tutorial itself (or a path under it) survives; any other Custom module
            // name, present or future, is excluded by default.
            '^modules' . $sep . 'Custom' . $sep . '(?!Tutorial(' . $sep . '|$))[^' . $sep_chars . ']+',
        );
        // Guard against the output file landing inside the tree being archived
        // (e.g. a bare filename with no path) and trying to zip itself.
        $real_file = realpath($dir) . DIRECTORY_SEPARATOR . basename($file);
        $real_cwd = realpath('.');
        if (str_starts_with($real_file, $real_cwd . DIRECTORY_SEPARATOR)) {
            $relative = substr($real_file, strlen($real_cwd) + 1);
            $exclude[] = '^' . preg_quote($relative, '#') . '$';
        }

        $st->writeln("Building distribution package: $file");
        $archive = new \BackupArchive($file);
        $ok = $archive->create('.', $exclude);

        if (!$ok) {
            $st->error('Failed to create distribution package.');
            return Command::FAILURE;
        }

        // Ship a manifest of every path this exact build placed in the zip, so a later
        // `update:apply-package` run on an installed copy of this release can diff "what
        // did the old release ship" against the new release it's upgrading to, and safely
        // remove only files Core itself shipped and no longer does - see
        // AI-private/release-packaging-plan.md. Read back via list_files() (the zip's own
        // authoritative content index) rather than re-deriving it from $exclude, so the
        // manifest can never drift from what's actually inside this exact archive. Written
        // after create() so the manifest doesn't list itself.
        $manifest_paths = $archive->list_files();
        $manifest_file = tempnam(sys_get_temp_dir(), 'epesi-dist-manifest-');
        file_put_contents($manifest_file, implode("\n", $manifest_paths) . "\n");
        $archive->addSingleFile($manifest_file, 'update/MANIFEST.txt');
        unlink($manifest_file);

        $st->success('Created: ' . $file . ' (' . round(filesize($file) / 1024 / 1024, 1) . ' MB, '
            . count($manifest_paths) . ' manifest entries)');
        return Command::SUCCESS;
    }
}
