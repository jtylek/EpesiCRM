<?php

namespace Epesi\Modules\MigrationWizard\Http;

use App\Support\Demo;
use App\Support\Setup\SetupState;
use Epesi\Modules\MigrationWizard\Detection\LegacyPresence;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the diagnostic report written by the scan (storage/app/migration-wizard/assessment.html|txt),
 * available under the same conditions as the scan page itself: a legacy installation that is not installed yet.
 */
final class AssessmentReportController
{
    public function __invoke(string $format): Response
    {
        abort_if(Demo::enabled() || SetupState::isInstalled() || ! LegacyPresence::detected(), 404);
        abort_unless(in_array($format, ['html', 'txt'], true), 404);

        $path = storage_path('app/migration-wizard/assessment.'.$format);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $format === 'html' ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
