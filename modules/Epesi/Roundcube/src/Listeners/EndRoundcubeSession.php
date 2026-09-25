<?php

namespace Epesi\Modules\Roundcube\Listeners;

use Epesi\Modules\Roundcube\Roundcube;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logging out of Epesi logs out of the Mailbox too: Roundcube's session is
 * its own, so without this it would stay open in the browser.
 */
class EndRoundcubeSession
{
    public function __construct(protected Request $request) {}

    public function handle(): void
    {
        $id = $this->request->cookie(Roundcube::SESSION_COOKIE);
        $table = Roundcube::table('session');

        if (is_string($id) && $id !== '' && Schema::hasTable($table)) {
            DB::table($table)->where('sess_id', $id)->delete();
        }
    }
}
