<?php

namespace Epesi\Modules\MigrationWizard\Filament\Pages;

use App\Support\Demo;
use App\Support\Setup\SetupState;
use Epesi\Modules\MigrationWizard\Checks\AssessmentSummary;
use Epesi\Modules\MigrationWizard\Checks\MigrationDryRun;
use Epesi\Modules\MigrationWizard\Checks\MigrationExecutionAdvisor;
use Epesi\Modules\MigrationWizard\Checks\VendorResidue;
use Epesi\Modules\MigrationWizard\Detection\LegacyConfigReader;
use Epesi\Modules\MigrationWizard\Detection\LegacyInstallation;
use Epesi\Modules\MigrationWizard\Detection\LegacyInventory;
use Epesi\Modules\MigrationWizard\Detection\LegacyPresence;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Read-only scan of the legacy installation, started by the "Scan my epesi installation" button (the page
 * opens at once with the version read from the legacy files; the scan shows a spinner, then the short
 * module summary). It changes nothing. There is no setup
 * code: the page exists only while the system is not installed, outside demo mode, and when a legacy
 * installation (data/config.php) is detected. The administrator has already made a backup (the progress
 * page asks to confirm it), so this page only reports what was found and links to the progress page.
 */
final class MigrateLegacy extends SimplePage
{
    /** Wide enough that the module table fits without a scrollbar. */
    protected Width|string|null $maxWidth = Width::FiveExtraLarge;

    public ?array $report = null;

    public bool $scanFailed = false;

    /** The panel is shared with the install wizard ("epesi setup"); these pages are the migration wizard. */
    public function boot(): void
    {
        Filament::getCurrentPanel()?->brandName('epesi migration wizard');
    }

    public function mount(): void
    {
        $this->ensureAvailable();
    }

    public function getTitle(): string|Htmlable
    {
        return 'epesi migration wizard';
    }

    /** The panel's brand already reads "epesi migration wizard"; a heading that repeats it would show it twice. */
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // Html, not Text: Text renders an inline span that shrinks to its content, so nothing could be centred.
            Html::make(fn (): HtmlString => new HtmlString('<div style="width:100%">'.$this->body().'</div>')),
        ]);
    }

    private function body(): string
    {
        if ($this->scanFailed) {
            return $this->versionLine()
                .'<p>The legacy installation could not be scanned. Check the server logs and the database configuration.</p>'
                .'<p><a href="#" class="underline" wire:click.prevent="scan">Scan again</a></p>';
        }
        if ($this->report === null) {
            $residue = app(VendorResidue::class)->detect(base_path());
            if ($residue !== []) {
                return $this->versionLine().$this->vendorResidueWarning($residue);
            }

            // The version is read from the legacy files only (no database work), so the page opens at once;
            // the scan itself starts when the administrator asks for it.
            return $this->versionLine().Blade::render(<<<'BLADE'
                <div style="margin-top:1rem;display:flex;justify-content:center">
                    <div wire:loading.remove wire:target="scan" style="text-align:center">
                        <x-filament::button wire:click="scan">Scan my epesi installation</x-filament::button>
                    </div>
                    <div wire:loading.flex wire:target="scan" style="align-items:center;justify-content:center;gap:.5rem">
                        <x-filament::loading-indicator class="h-5 w-5" />
                        <span>Scanning your data...</span>
                    </div>
                </div>
                BLADE);
        }

        $summaries = app(AssessmentSummary::class);
        $report = $this->report;
        if (is_string($report['saved_to'] ?? null)) {
            $report['report_links'] = [
                'html' => route('filament.setup.migrate.report', ['format' => 'html']),
                'txt' => route('filament.setup.migrate.report', ['format' => 'txt']),
            ];
        }
        $out = $this->versionLine().$summaries->html($report);
        if ($report['legacy_signature'] ?? false) {
            $out .= Blade::render(<<<'BLADE'
                <div style="margin-top:1rem;display:flex;justify-content:center">
                    <x-filament::button tag="a" :href="$href">Start migration</x-filament::button>
                </div>
                BLADE, ['href' => route('filament.setup.migrate.progress')]);
        }

        return $out;
    }

    /**
     * The old Epesi's own vendor/ was not removed before this release was unpacked over it (Upgrade_guide.md,
     * step before unpacking): the wizard stops here, before scanning anything, because the later cleanup does
     * not expect vendor/ to hold anything the release didn't put there itself.
     *
     * @param  list<string>  $residue
     */
    private function vendorResidueWarning(array $residue): string
    {
        $folders = implode(', ', array_map(static fn (string $f): string => 'vendor/'.$f, $residue));

        return '<div style="max-width:40rem;margin:0 auto" class="rounded-lg border border-danger-500 p-3 text-danger-700">'
            .'<p><strong>The old Epesi\'s own vendor/ directory is still here.</strong> '
            .'It was not deleted before this release was unpacked over it, so these folders from the old '
            .'installation are still inside vendor/ and were not part of this release: <strong>'.e($folders).'</strong>.</p>'
            .'<p style="margin-top:.75rem">To fix it:</p>'
            .'<ol style="margin:.25rem 0 0 1.25rem;list-style:decimal">'
            .'<li>Delete the old <code>/vendor</code> directory.</li>'
            .'<li>Unzip the Epesi 3.0 release package again.</li>'
            .'<li>Refresh this page to restart the wizard.</li>'
            .'</ol>'
            .'</div>';
    }

    /** The scan's version once there is one, otherwise the one in the legacy files (EPESI_VERSION). */
    private function versionLine(): string
    {
        $version = $this->report['version'] ?? LegacyInventory::readFileVersion(base_path());

        $database = null;
        try {
            $database = (new LegacyConfigReader)->read(base_path('data/config.php'))['DATABASE_NAME'] ?? null;
        } catch (Throwable) {
            // The name is only informative here; the scan reports a real problem with the configuration.
        }

        $lines = [
            'Legacy Epesi data detected: <strong>Version: '.e((string) ($version ?: 'unknown')).'</strong>',
            'Database: <strong>'.e((string) ($database ?: 'unknown')).'</strong>',
            is_dir(base_path('data')) ? '/data directory found' : '/data directory NOT found',
        ];

        return '<p style="margin:0 0 1rem;line-height:1.6;text-align:center">'.implode('<br>', $lines).'</p>';
    }

    private function ensureAvailable(): void
    {
        abort_if(Demo::enabled() || SetupState::isInstalled() || ! LegacyPresence::detected(), 404);
    }

    /** The read-only scan; also behind "Scan again". Nothing is shown from it except the short summary. */
    public function scan(): void
    {
        $this->ensureAvailable();

        try {
            $this->scanFailed = false;
            $full = app(LegacyInstallation::class)->assess(base_path('data/config.php'));
            $full['dry_run_preview'] = app(MigrationDryRun::class)->preview($full);
            $full['execution_advice'] = app(MigrationExecutionAdvisor::class)->assess($full, base_path());
            // The page keeps only the short summary; the full report goes to a file for diagnostics.
            $summaries = app(AssessmentSummary::class);
            $this->report = $summaries->summarize($full);
            try {
                $this->report['saved_to'] = $summaries->save($full);
            } catch (Throwable) {
                $this->report['saved_to'] = null;
            }
        } catch (Throwable) {
            // Do not leak credentials or database server error messages to the browser.
            $this->report = null;
            $this->scanFailed = true;
        }
    }
}
