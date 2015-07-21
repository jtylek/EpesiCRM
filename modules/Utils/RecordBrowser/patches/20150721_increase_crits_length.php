<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$checkpoint = Patch::checkpoint('mark_to_index');
if(!$checkpoint->is_done()) {
    $done = $checkpoint->get('recordsets', array());
    $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
    foreach ($recordsets as $tab => $caption) {
        Patch::require_time(1);
        if (!isset($done[$tab])) {
            PatchUtil::db_alter_column($tab . '_access', 'crits', 'X');
            $done[$tab] = true;
            $checkpoint->set('recordsets', $done);
        }
    }
    $checkpoint->done();
}
