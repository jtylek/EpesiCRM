<?php

namespace Epesi\Modules\MigrationWizard\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration → Migration Report: the files of a finished migration. The administration panel already admits
 * only super administrators (User::canAccessPanel) and answers 404 in demo mode.
 */
final class MigrationReportAdministrationController
{
    public function __invoke(Request $request, string $format): Response
    {
        return MigrationReportFiles::serve($request, $format);
    }
}
