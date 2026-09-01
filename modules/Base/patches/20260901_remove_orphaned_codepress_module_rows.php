<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Libs_Codepress (vendored CodePress 0.9.6 syntax-highlighting editor, wrapped as a QuickForm
// element type) and Tests_Codepress (its demo module) were removed entirely (2026-09-01) - the
// element type had no callers left besides the demo itself. Both install()/uninstall() were
// always just `return true;` - neither ever created any schema of its own, so the only trace
// left behind on an instance that had them installed is these two tracking rows. Safe/idempotent:
// guarded by both an existence check and confirming the module's code is actually gone, same
// pattern as modules/Base/patches/20260822_remove_orphaned_scriptaculous_module_row.php.
foreach (array('Libs_Codepress', 'Tests_Codepress') as $m) {
    if (DB::GetOne('SELECT 1 FROM modules WHERE name=%s', array($m))
        && !is_dir('modules/'.str_replace('_', '/', $m))) {
        DB::Execute('DELETE FROM modules WHERE name=%s', array($m));
    }
}
