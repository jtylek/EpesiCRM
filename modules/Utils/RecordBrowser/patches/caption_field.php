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
    PatchUtil::db_add_column($tab, 'caption', 'C(255)');
    $checkpoint->set('processed', $processed);
}
