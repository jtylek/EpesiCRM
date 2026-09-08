<?php

/**
 * Deny direct web access to data/ on existing installs.
 *
 * data/ has never shipped an access-control file of its own (only a placeholder
 * data/index.html), and root .htaccess has no data/-specific deny rule either - config.php is
 * self-protected via its own `defined('_VALID_ACCESS') || die()` guard, but nothing else under
 * data/ is: logs (including php_errors.log, which can contain paths/stack traces), any stray
 * non-.php file, per-instance uploads depending on module design - all served as plain static
 * files to anyone who knows or guesses the path. Confirmed no template/front-end code links
 * directly into data/ (everything that serves stored files - e.g.
 * modules/Utils/FileDownload/download.php - reads DATA_DIR server-side and streams through
 * PHP), so denying the whole directory has no functional cost.
 *
 * Fresh checkouts/installs now get data/.htaccess as a tracked file (see .gitignore) the same
 * way they already get data/index.html - but an existing install only picks up a newly-tracked
 * file if its deployment method re-syncs the full file tree. An install that only ever runs
 * update.php's patch chain against its current files (never re-extracts a package/re-clones)
 * would otherwise never get it, so this patch writes the file directly, matching the content
 * shipped in data/.htaccess.
 *
 * Idempotent: only writes the file if it isn't already there.
 *
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$path = DATA_DIR . '/.htaccess';
if (!file_exists($path)) {
    file_put_contents($path, <<<'HTACCESS'
# deny webserver access to this directory
<ifModule mod_authz_core.c>
    Require all denied
</ifModule>
<ifModule !mod_authz_core.c>
    Deny from all
</ifModule>

HTACCESS
    );
}
