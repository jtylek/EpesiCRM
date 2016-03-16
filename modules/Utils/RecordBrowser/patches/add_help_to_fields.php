<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
$checkpoint = Patch::checkpoint('recordset');
$processed = $checkpoint->get('processed', array());
foreach ($recordsets as $tab => $caption) {
    if (isset($processed[$tab])) {
        continue;
    }
    $processed[$tab] = true;
    Patch::require_time(3);
    $tab = $tab . "_field";
    $columns = DB::MetaColumnNames($tab);
    if (!isset($columns['HELP'])) {
        PatchUtil::db_add_column($tab, 'help', 'X');
    }
    $checkpoint->set('processed', $processed);
}
