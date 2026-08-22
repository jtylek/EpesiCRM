<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// this patch has been renamed to make update process right
// 4bb2048388cd0807ac707edeaa67dfa2 is:
// modules/Utils/RecordBrowser/patches/20140814_add_processing_order.php

$db = new PatchesDB();
if ($db->was_applied('4bb2048388cd0807ac707edeaa67dfa2')) {
    return;
}

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
    if (!isset($columns['PROCESSING_ORDER'])) {
        PatchUtil::db_add_column($tab, 'processing_order', 'I2');
        DB::Execute("UPDATE $tab SET processing_order=position");
    }
    $checkpoint->set('processed', $processed);
}
