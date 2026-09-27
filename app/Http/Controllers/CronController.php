<?php

namespace App\Http\Controllers;

use App\Services\Cron\CronRunner;
use App\Services\Cron\CronToken;
use App\Support\Setup\SetupState;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The cron URL — Epesi's cron.php?token=…: runs cron over the web, for a
 * host whose cron can only call an address, or an outside service that does
 * (Administration → Cron shows the URL). The token is the only lock, so a
 * wrong one gets nothing else. Plain text, for whatever calls it.
 */
class CronController extends Controller
{
    public function __invoke(Request $request, CronRunner $runner): Response
    {
        if (! SetupState::isInstalled()) {
            return $this->text('epesi is not set up yet.', 503);
        }

        if (! CronToken::matches($request->query('token'))) {
            return $this->text('Missing or wrong token: copy the cron URL from Administration → Cron.', 403);
        }

        // An outside service may stop waiting; the tasks carry on.
        ignore_user_abort(true);
        @set_time_limit(0);

        return $this->text(CronRunner::report($runner->runDue('url')));
    }

    protected function text(string $text, int $status = 200): Response
    {
        return response($text."\n", $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
