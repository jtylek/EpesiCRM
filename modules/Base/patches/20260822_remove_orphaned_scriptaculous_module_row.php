<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Libs_ScriptAculoUs was removed entirely (2026-07-30, commit 255a5256b) - a legacy
// Prototype.js-era JS animation/autocomplete library, replaced by vanilla CSS transitions.
// Its install()/uninstall() were always just `return true;` (see
// AI-shared/deliberate-removals.md's "Orphaned modules DB row after upgrade" entry for the full
// reasoning) - it never created any schema of its own, so the only trace left behind on an
// instance that had it installed is this one tracking row. Safe/idempotent: guarded by both an
// existence check and confirming the module's code is actually gone, same pattern as
// modules/Base/patches/remove_old_modules.php.
if (DB::GetOne('SELECT 1 FROM modules WHERE name=%s', array('Libs_ScriptAculoUs'))
    && !is_dir('modules/Libs/ScriptAculoUs')) {
    DB::Execute('DELETE FROM modules WHERE name=%s', array('Libs_ScriptAculoUs'));
}
