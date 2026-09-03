<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Seven modules/Tests/* demo modules were deleted in the 2026-09-01 trim of that tree
// (see AI-shared/dont-reintroduce.md) - each was a three-to-ten-line "call this widget"
// snippet teaching nothing the widget's own signature doesn't, and Tests_Search/Tests_Menu
// additionally demonstrated hooks that no longer have any consumer (advanced_search,
// advanced_search_access, quick_menu). All seven had install()/uninstall() that were always
// just `return true;` - none ever created schema of its own (the one demo module that did,
// Tests_Calendar_Event with its tests_calendar_event table, was deliberately KEPT), so the
// only trace left behind on an instance that had them installed is these tracking rows.
// Safe/idempotent: guarded by both an existence check and confirming the module's code is
// actually gone, same pattern as
// modules/Base/patches/20260901_remove_orphaned_codepress_module_rows.php.
//
// Tests_Lang shipped 35 lang/<code>.php files; those are shipped defaults inside the deleted
// module dir, not DB rows, so they need no cleanup here. Per-instance custom translation
// overrides live at data/Base_Lang/custom/<module>/<code>.php and are left alone deliberately -
// they are user-entered data, and Base_LangCommon simply ignores entries for a module that is
// no longer installed.
// `available_modules` is cleared too. It is only a scan cache of what is on disk
// (Base_SetupCommon::refresh_available_modules() TRUNCATEs and rescans, and runs on any
// install and on every Setup-screen load), so it does self-heal - but until something
// triggers that refresh, a stale row keeps the deleted module visible as "Not installed"
// in `console.php module:list` and on the Setup screen. Cheap to clear here so the
// post-patch state is correct immediately rather than eventually.
foreach (array(
    'Tests_Attachment',
    'Tests_Comment',
    'Tests_Image',
    'Tests_Lang',
    'Tests_Menu',
    'Tests_Search',
    'Tests_TabbedBrowser',
) as $m) {
    if (is_dir('modules/'.str_replace('_', '/', $m))) continue;
    if (DB::GetOne('SELECT 1 FROM modules WHERE name=%s', array($m))) {
        DB::Execute('DELETE FROM modules WHERE name=%s', array($m));
    }
    DB::Execute('DELETE FROM available_modules WHERE name=%s', array($m));
}
