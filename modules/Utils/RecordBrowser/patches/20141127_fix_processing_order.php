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
    Patch::require_time(5);
    $tab = $tab . "_field";
    DB::Execute("UPDATE $tab SET processing_order=position WHERE processing_order IS NULL");
    PatchUtil::db_alter_column($tab, 'processing_order', 'I2 NOTNULL');
    $checkpoint->set('processed', $processed);
}
