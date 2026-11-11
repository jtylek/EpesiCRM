<?php

namespace Epesi\Modules\MigrationWizard\Http;

use App\Services\LegacyImport\TimeBudget;
use App\Support\Demo;
use App\Support\Setup\SetupState;
use Epesi\Modules\MigrationWizard\Orchestration\PanelRun;
use Epesi\Modules\MigrationWizard\Orchestration\RunnerFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The migration summary files, under the same conditions as the progress page they are linked from. After the
 * migration is finished that page is gone: Administration → Migration Report serves the same files then.
 */
final class MigrationReportController
{
    public function __invoke(Request $request, string $format = 'html'): Response
    {
        abort_if(Demo::enabled(), 404);
        $run = new PanelRun(RunnerFactory::make(), RunnerFactory::state(), app(TimeBudget::class));
        abort_unless($run->available(SetupState::isInstalled()), 404);

        return MigrationReportFiles::serve($request, $format);
    }
}
