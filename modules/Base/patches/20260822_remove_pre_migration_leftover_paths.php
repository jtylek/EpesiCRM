<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Deletes on-disk files/directories that were removed from the tracked source tree at various
// points during the PHP 7.4 -> 8.2 migration (see AI-shared/MIGRATION_NOTES.md), but that a plain
// git checkout/pull never leaves behind on disk. This patch exists for installs that instead get
// upgraded by copying/extracting a new release directly over an old one (e.g. cPanel/Softaculous-
// style deploys, or any rsync/robocopy-style merge that only overwrites-and-adds and never
// deletes) - on those, every one of these old paths survives untouched release after release since
// nothing ever tells the filesystem it's gone. Modeled directly on the `git clean -fd` cleanup
// done by hand for the kancelaria.epesi.cloud cutover (MIGRATION_NOTES.md SS71); this patch is the
// same cleanup for installs with no .git to lean on.
//
// Every path below was confirmed (via git history on this repo) to have actually been removed from
// the tracked tree by a specific commit - this is not a guess at what "looks old". Safe/idempotent:
// each entry is checked with file_exists() first, so already-clean installs (or a second run after
// a partial failure) just skip everything.
//
// Deliberately NOT in scope: anything under vendor/ (Composer's own job - run `composer install`,
// which already prunes packages no longer in composer.lock) or data/ (per-instance data, never
// touched by cleanup like this). Also not in scope: hosting-artifact files like Softaculous
// bookkeeping or a renamed-aside old .htaccess backup - those have host-specific, non-constant
// names or need eyeballing before deletion, so they were handled by hand for kancelaria rather than
// automated here.

$old_paths = array(
    // Prototype.js / script.aculo.us removal (2026-08-06, 6d4e3257f) and the pre-AdminLTE mobile
    // subsystem removal (2026-07-28, see deliberate-removals.md)
    'libs/prototype.js',
    'libs/UiUIKit',
    'mobile.php',
    'mobile.css',

    // ADOdb replaced by composer's adodb/adodb-php (2026-08-18, 891a605b0)
    'libs/adodb',

    // Roundcube vendor tree relocated modules/CRM/Roundcube/RC -> modules/Libs/RoundCube/RC
    // (2026-08-04, a4fe37c7b)
    'modules/CRM/Roundcube/RC',
    'modules/CRM/Roundcube/.htaccess',

    // CKEditor replaced by Quill (2026-08-11, 8d47bec1b) - the module's own *Install.php/Common_0.php
    // are deliberately kept as an empty shell (see deliberate-removals.md), only the vendored
    // editor/JS glue is stale
    'modules/Libs/CKEditor/ckeditor',
    'modules/Libs/CKEditor/ck.js',
    'modules/Libs/CKEditor/ckeditor.php',
    'modules/Libs/CKEditor/onsubmit.js',

    // Old pre-composer vendored QuickForm, superseded by openpsa/quickform (removed 2026-08-19, §50)
    'modules/Libs/QuickForm/3.2.14-php7',

    // OpenFlashChart replaced by ChartJS (2026-08-18, 83979cfaf) - same "*Install.php kept as an
    // empty shell" tradeoff as CKEditor, only the vendored Flash .swf tree + its endpoint are stale
    'modules/Libs/OpenFlashChart/2-lug',
    'modules/Libs/OpenFlashChart/data.php',
    'modules/Tests/OpenFlashChart',

    // script.aculo.us vendored copy, dependency dropped entirely (removed earlier, 255a5256b)
    'modules/Libs/ScriptAculoUs',

    // Old hand-vendored PHPExcel classes, superseded by the composer-managed PhpSpreadsheet-based
    // vendor/ tree already inside this same module dir (6e2a3a336)
    'modules/Libs/PHPExcel/vendor/phpoffice/phpexcel',

    // Old admin/ panel files superseded by later admin-UI rewrites
    'admin/modules/LangUp.php',   // 9def5a5ea - per-module live translation resolution replaced this
    'admin/modules/ThemeUp.php',  // e90e00355 - Theme Updater utility removed
    'admin/modules/PhpInfo.php',  // 1b815b863 - split into admin/modules/PhpInfo/PhpInfo.php
    'admin/modules/Patches/patches.css', // 1b815b863 - admin restyle, inline AdminLTE styling instead

    // Empty test-skeleton, not shipped by this branch (matches SS51/SS52 "removed the skeleton")
    'codeception.yml',
    'tests',
);

foreach ($old_paths as $path) {
    if (file_exists($path)) {
        recursive_rmdir($path);
        PatchUtil::log("Removed pre-migration leftover: $path\n");
    }
}
