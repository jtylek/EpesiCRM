<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties');
foreach($tabs as $tab) {
    PatchUtil::db_drop_column($tab.'_data_1', 'private');
}