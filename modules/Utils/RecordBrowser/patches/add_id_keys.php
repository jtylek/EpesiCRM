<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// this patch has been splitted from the other one.
// 8fd8dd050f628900ac035305d0cab140 is:
// modules/Utils/RecordBrowser/patches/20140826_add_id_key_to_tables2.php

$db = new PatchesDB();
if ($db->was_applied('8fd8dd050f628900ac035305d0cab140')) {
    return;
}

$columns = DB::MetaColumnNames('recordbrowser_table_properties');
if (!isset($columns['ID'])) {
  $tab_ids_checkpoint = Patch::checkpoint('tab_ids');
  if(!$tab_ids_checkpoint->is_done()) {
    Patch::require_time(20);

    if(DB::is_postgresql()) {
        DB::Execute('ALTER TABLE recordbrowser_table_properties DROP CONSTRAINT recordbrowser_table_properties_pkey');
        DB::Execute('ALTER TABLE recordbrowser_table_properties ADD COLUMN id SERIAL PRIMARY KEY');
    } else {
        DB::Execute('ALTER TABLE recordbrowser_table_properties DROP PRIMARY KEY');
        DB::Execute('ALTER TABLE recordbrowser_table_properties ADD id SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY');
    }
    DB::CreateIndex('recordbrowser_table_properties_tab','recordbrowser_table_properties','tab',array('UNIQUE'=>1));
    $tab_ids_checkpoint->done();
  }
}

$field_ids_checkpoint = Patch::checkpoint('field_ids');
if(!$field_ids_checkpoint->is_done()) {
    Patch::require_time(20);

    $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
    foreach ($recordsets as $tab => $caption) {
        $tab_f = $tab . "_field";
        $columns = DB::MetaColumnNames($tab_f);
        if (!isset($columns['ID'])) {
            if(DB::is_postgresql()) {
                @DB::Execute('ALTER TABLE '.$tab_f.' DROP CONSTRAINT '.$tab_f.'_pkey');
                DB::Execute('ALTER TABLE '.$tab_f.' ADD COLUMN id SERIAL PRIMARY KEY');
            } else {
                @DB::Execute('ALTER TABLE '.$tab_f.' DROP PRIMARY KEY');
                DB::Execute('ALTER TABLE '.$tab_f.' ADD id SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY');
            }
            DB::CreateIndex($tab_f.'_field',$tab_f,'field',array('UNIQUE'=>1));
        }
    }
    $field_ids_checkpoint->done();
}
