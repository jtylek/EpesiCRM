<?php

/**
 * Remove legacy library files left behind by the epesi-adminlte migration.
 *
 * That migration replaced several bundled/vendored libraries in place:
 * TCPDF, PHPExcel and the PEAR QuickForm copy moved from a hand-bundled
 * folder under the owning module to a composer-managed vendor/ folder in
 * the same spot; CKEditor and OpenFlashChart were swapped for Quill and
 * ChartJS (see AI-shared/dont-reintroduce.md), leaving their
 * module folders behind as thin install/common-class stubs; Roundcube
 * moved from modules/CRM/Roundcube/RC to modules/Libs/RoundCube/RC; and
 * the front-end libs/ folder moved from Prototype.js/jQuery UI theme
 * (UiUIKit) to Bootstrap 5/AdminLTE 4.
 *
 * On any install that upgrades by overwriting files in place - a dist zip
 * extracted over an existing install, same as a plain `git checkout` -
 * none of that old content is automatically deleted, since the upgrade
 * only adds/overwrites paths that exist in the new release; whatever an
 * older release had that the new one doesn't just keeps sitting there.
 * This patch clears it out.
 *
 * Lives in Base (not e.g. Libs) because Base is the only module
 * guaranteed to be installed, so its patches/ dir is always scanned
 * (PatchUtil::list_patches() only scans patches/ for currently-installed
 * modules - see include/patches.php).
 *
 * Approach: for each reorganized directory, keep only the entries the
 * current codebase actually ships there and delete anything else found
 * alongside them. Whitelisting current entries - rather than hardcoding
 * old paths like "tcpdf5.9" or "3.2.11" - means this doesn't need to know
 * which exact older version a given install was on.
 *
 * Idempotent: re-running only ever finds already-clean directories (or
 * ones that were never bundled at all, e.g. a fresh install), so nothing
 * happens on subsequent runs.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$legacy_dirs = array(
    'modules/Libs/TCPDF' => array('.hidden', 'TCPDFCommon_0.php', 'TCPDFInstall.php', 'TCPDF_0.php', 'composer.json', 'composer.lock', 'download.php', 'tcpdf_config.php', 'theme', 'vendor', 'patches'),
    'modules/Libs/PHPExcel' => array('.hidden', 'PHPExcelCommon_0.php', 'PHPExcelInstall.php', 'composer.json', 'composer.lock', 'vendor', 'patches'),
    'modules/Libs/CKEditor' => array('CKEditorCommon_0.php', 'CKEditorInstall.php', 'patches'),
    'modules/Libs/OpenFlashChart' => array('OpenFlashChartInstall.php', 'OpenFlashChart_0.php', 'patches'),
    'modules/Libs/QuickForm' => array('.hidden', 'FieldTypes', 'QuickFormCommon_0.php', 'QuickFormInstall.php', 'QuickForm_0.php', 'Renderer', 'Rule', 'autohide_fields.js', 'epesi-qf.patch', 'requires.php', 'select.js', 'theme', 'theme_adminltedark', 'patches'),
    'modules/CRM/Roundcube' => array('RemoteAttachment.php', 'RoundcubeCommon_0.php', 'RoundcubeInstall.php', 'Roundcube_0.php', 'help', 'patches', 'theme'),
    'libs' => array('HistoryKeeper.js', 'adminlte-4.1.0', 'bootstrap-5.3.8', 'bootstrap-icons-1.13.1', 'fullcalendar-6.1.21', 'jquery-1.11.3.js', 'jquery-1.11.3.min.js', 'jquery-migrate-1.2.1.js', 'jquery-migrate-1.2.1.min.js', 'jquery-ui-1.10.1.custom.min.css', 'jquery-ui-1.10.1.custom.min.js', 'lgpl-3.0.txt', 'minify'),
);

$removed = 0;
foreach ($legacy_dirs as $dir => $keep) {
    Patch::require_time(2);

    if (!is_dir($dir)) {
        continue;
    }
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, $keep, true)) {
            continue;
        }
        $path = "$dir/$entry";
        PatchUtil::log("Removing legacy path: $path\n");
        recursive_rmdir($path);
        $removed++;
    }
}

PatchUtil::log("Legacy adminlte-migration cleanup: removed $removed path(s)\n");
