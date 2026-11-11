<?php

namespace Epesi\Modules\MigrationWizard\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Epesi\Modules\MigrationWizard\Report\MigrationReport;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * What the migration from a legacy Epesi wrote when it ended: the summary report (modules, tables, record counts)
 * and migration_log.txt (the same summary and the warnings per module). Shown in the menu only once those files
 * exist; nothing is generated here.
 */
class MigrationReportPage extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|UnitEnum|null $navigationGroup = 'Server Setup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Migration Report';

    protected static ?string $title = 'Migration Report';

    protected static ?string $slug = 'migration-report';

    public static function canAccess(): bool
    {
        return MigrationReport::exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return MigrationReport::exists();
    }

    public function content(Schema $schema): Schema
    {
        $link = static fn (string $format, array $query = [], bool $newWindow = true): string => '<a class="fi-link underline" href="'
            .e(route('filament.administration.migration-report.file', ['format' => $format] + $query)).'"'
            .($newWindow ? ' target="_blank" rel="noopener"' : '').'>';

        $written = is_file(MigrationReport::path()) ? date('Y-m-d H:i:s', (int) filemtime(MigrationReport::path())) : null;

        return $schema->components([
            Section::make(__('Migration from legacy Epesi'))
                ->description($written === null ? null : __('Written when the migration ended: :time', ['time' => $written]))
                ->schema([
                    Text::make(new HtmlString(
                        '<p>'.$link('html').e(__('Open the migration report in a new window')).'</a> '.e(__('(modules, tables and record counts)')).'</p>'
                        .'<p>'.e(__('Detailed log with the warnings per module (migration_log.txt):')).' '
                        .$link('txt').e(__('open in a new window')).'</a> '.e(__('or')).' '
                        .$link('txt', ['download' => 1], false).e(__('download')).'</a>.</p>'
                    )),
                ]),
        ]);
    }
}
