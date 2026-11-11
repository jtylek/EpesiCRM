<?php

namespace Epesi\Modules\MigrationWizard\Http;

use Epesi\Modules\MigrationWizard\Report\MigrationReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the two files written when the migration ended: the HTML report and migration_log.txt. Used by the
 * migration page's own route and by Administration, after the migration page is gone. Nothing is generated here.
 */
final class MigrationReportFiles
{
    public static function serve(Request $request, string $format): Response
    {
        abort_unless(MigrationReport::exists(), 404);
        $headers = ['Cache-Control' => 'no-store'];

        if ($format === 'txt') {
            abort_unless(is_file(MigrationReport::logPath()), 404);
            $headers['Content-Type'] = 'text/plain; charset=utf-8';

            // ?download=1 saves it as migration_log.txt; without it the text opens in the window.
            return $request->boolean('download')
                ? response()->download(MigrationReport::logPath(), 'migration_log.txt', $headers)
                : response()->file(MigrationReport::logPath(), $headers);
        }

        return response()->file(MigrationReport::path(), $headers + ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
