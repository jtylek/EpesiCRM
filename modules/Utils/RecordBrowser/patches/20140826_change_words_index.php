<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// this patch has been splitted from the other one.
$db = new PatchesDB();
if ($db->was_applied('8fd8dd050f628900ac035305d0cab140')) {
    return;
}

DB::Execute('TRUNCATE TABLE recordbrowser_words_map');

$field_ids_checkpoint = Patch::checkpoint('field_ids');
if(!$field_ids_checkpoint->is_done()) {
    Patch::require_time(20);

    $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
    foreach ($recordsets as $tab => $caption) {
        DB::Execute('UPDATE '.$tab.'_data_1 SET indexed=0');
    }
    $field_ids_checkpoint->done();
}

$tab_id_col_checkpoint = Patch::checkpoint('tab_id_col');
if(!$tab_id_col_checkpoint->is_done()) {
    Patch::require_time(20);

    PatchUtil::db_add_column('recordbrowser_words_map', 'tab_id', 'I2');
    PatchUtil::db_add_column('recordbrowser_words_map', 'field_id', 'I2');
    if(DB::is_postgresql()) {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD CONSTRAINT tab_id_fk FOREIGN KEY (tab_id) REFERENCES recordbrowser_table_properties');
    } else {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD FOREIGN KEY (tab_id) REFERENCES recordbrowser_table_properties(id)');
    }
    $tab_id_col_checkpoint->done();
}

$remove_idx_checkpoint = Patch::checkpoint('remove_idx');
if(!$remove_idx_checkpoint->is_done()) {
    Patch::require_time(20);

    if(DB::is_mysql()) {
        $a = DB::GetRow('SHOW CREATE TABLE recordbrowser_words_map');
        if(preg_match('/CONSTRAINT (.+) FOREIGN KEY .*word_id/',$a[1],$m))
            DB::Execute('alter table `recordbrowser_words_map` drop foreign key '.$m[1]);
    } else {
        $a = DB::GetOne("SELECT
            tc.constraint_name, tc.table_name, kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name
        FROM
            information_schema.table_constraints AS tc
        JOIN information_schema.key_column_usage AS kcu
          ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage AS ccu
          ON ccu.constraint_name = tc.constraint_name
        WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name='recordbrowser_words_map' AND kcu.column_name='word_id';");
        if($a) {
            DB::Execute('alter table recordbrowser_words_map drop CONSTRAINT "'.$a.'"');
        }
    }
    DB::CreateIndex('recordbrowser_words_map__idx','recordbrowser_words_map','word_id,tab,record_id,field_name',array('DROP'=>1));
    DB::CreateIndex('recordbrowser_words_map__idx2','recordbrowser_words_map','tab,record_id',array('DROP'=>1));
    
    DB::Execute('TRUNCATE TABLE recordbrowser_words_index');
    
    $remove_idx_checkpoint->done();
}

$finalize_checkpoint = Patch::checkpoint('finalize');
if(!$finalize_checkpoint->is_done()) {
    Patch::require_time(20);

    PatchUtil::db_drop_column('recordbrowser_words_map', 'tab');
    PatchUtil::db_drop_column('recordbrowser_words_map', 'field_name');

    if(DB::is_postgresql()) {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD CONSTRAINT word_id_fk FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index');
    } else {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index(id)');
    }
    DB::CreateIndex('recordbrowser_words_map__idx','recordbrowser_words_map','word_id,tab_id');
    $finalize_checkpoint->done();
}
