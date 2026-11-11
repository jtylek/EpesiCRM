<?php

namespace Epesi\Modules\MigrationWizard\Http;

use App\Services\LegacyImport\TimeBudget;
use App\Support\Demo;
use App\Support\Setup\SetupState;
use Epesi\Modules\MigrationWizard\Import\ImportProgress;
use Epesi\Modules\MigrationWizard\Orchestration\PanelRun;
use Epesi\Modules\MigrationWizard\Orchestration\RunnerFactory;
use Illuminate\Http\JsonResponse;

/**
 * What the migration is doing right now, for the progress page's status lines. The page asks every two seconds,
 * independently of Livewire (which does not poll while one of its own long steps is in flight), so the lines move
 * during a long browser step too. Read-only, no lock taken; available under the same conditions as the page.
 */
final class MigrationProgressJsonController
{
    public function __invoke(): JsonResponse
    {
        abort_if(Demo::enabled(), 404);
        $run = new PanelRun(RunnerFactory::make(), RunnerFactory::state(), app(TimeBudget::class));
        abort_unless($run->available(SetupState::isInstalled()), 404);

        return response()->json([
            'line' => ImportProgress::line(),
            'speed' => ImportProgress::speed(),
            'estimate' => ImportProgress::estimate(),
            'lock_held' => RunnerFactory::lock()->isHeld(),
        ])->header('Cache-Control', 'no-store');
    }
}
