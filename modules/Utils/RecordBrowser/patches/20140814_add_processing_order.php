<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
foreach ($recordsets as $tab => $caption) {
    $tab = $tab . "_field";
    $columns = DB::MetaColumnNames($tab);
    if (!isset($columns['PROCESSING_ORDER'])) {
        PatchUtil::db_add_column($tab, 'processing_order', 'I4');
        DB::Execute("UPDATE $tab SET processing_order=position");
    }
}
