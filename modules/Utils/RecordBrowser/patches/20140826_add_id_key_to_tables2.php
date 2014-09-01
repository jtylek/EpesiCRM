<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('TRUNCATE TABLE recordbrowser_words_map');

$tab_ids_checkpoint = Patch::checkpoint('tab_ids');
if(!$tab_ids_checkpoint->is_done()) {
    Patch::require_time(20);

    if(DATABASE_DRIVER=='postgres') {
        DB::Execute('ALTER TABLE recordbrowser_table_properties DROP CONSTRAINT recordbrowser_table_properties_pkey');
        DB::Execute('ALTER TABLE recordbrowser_table_properties ADD COLUMN id SERIAL PRIMARY KEY');
    } else {
        DB::Execute('ALTER TABLE recordbrowser_table_properties DROP PRIMARY KEY');
        DB::Execute('ALTER TABLE recordbrowser_table_properties ADD id SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY');
    }
    DB::CreateIndex('recordbrowser_table_properties_tab','recordbrowser_table_properties','tab',array('UNIQUE'=>1));
    $tab_ids_checkpoint->done();
}

$field_ids_checkpoint = Patch::checkpoint('field_ids');
if(!$field_ids_checkpoint->is_done()) {
    Patch::require_time(20);

    $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
    foreach ($recordsets as $tab => $caption) {
        $tab_f = $tab . "_field";
        $columns = DB::MetaColumnNames($tab_f);
        if (!isset($columns['ID'])) {
            if(DATABASE_DRIVER=='postgres') {
                @DB::Execute('ALTER TABLE '.$tab_f.' DROP CONSTRAINT '.$tab_f.'_pkey');
                DB::Execute('ALTER TABLE '.$tab_f.' ADD COLUMN id SERIAL PRIMARY KEY');
            } else {
                @DB::Execute('ALTER TABLE '.$tab_f.' DROP PRIMARY KEY');
                DB::Execute('ALTER TABLE '.$tab_f.' ADD id SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY');
            }
            DB::CreateIndex($tab_f.'_field',$tab_f,'field',array('UNIQUE'=>1));
        }
        DB::Execute('UPDATE '.$tab.'_data_1 SET indexed=0');
    }
    $field_ids_checkpoint->done();
}

$tab_id_col_checkpoint = Patch::checkpoint('tab_id_col');
if(!$tab_id_col_checkpoint->is_done()) {
    Patch::require_time(20);

    PatchUtil::db_add_column('recordbrowser_words_map', 'tab_id', 'I2');
    PatchUtil::db_add_column('recordbrowser_words_map', 'field_id', 'I2');
    if(DATABASE_DRIVER=='postgres') {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD CONSTRAINT tab_id_fk FOREIGN KEY (tab_id) REFERENCES recordbrowser_table_properties');
    } else {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD FOREIGN KEY (tab_id) REFERENCES recordbrowser_table_properties(id)');
    }
    $tab_id_col_checkpoint->done();
}

$remove_idx_checkpoint = Patch::checkpoint('remove_idx');
if(!$remove_idx_checkpoint->is_done()) {
    Patch::require_time(20);

    if(DATABASE_DRIVER=='mysqli' || DATABASE_DRIVER=='mysqlt') {
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

/*$update_map_checkpoint = Patch::checkpoint('update_map');
if(!$update_map_checkpoint->is_done()) {
    if($update_map_checkpoint->has('tabs')) {
        $tabs = $update_map_checkpoint->get('tabs');
    } else {
        $tabs = DB::GetAssoc('SELECT id,tab FROM recordbrowser_table_properties');
    }
    foreach($tabs as $tab_id=>$tab) {
//        DB::Execute('UPDATE recordbrowser_words_map SET tab_id=%d WHERE tab=%s',array($tab_id,$tab));
        
        if($update_map_checkpoint->has('fields_'.$tab_id)) {
            $fields = $update_map_checkpoint->get('fields_'.$tab_id);
        } else {
            $fields = DB::GetAssoc('SELECT id,field FROM '.$tab.'_field');
        }
        foreach($fields as $field_id=>$field) {
            $update_map_checkpoint->require_time(10);
            
            DB::Execute('UPDATE recordbrowser_words_map SET tab_id=%d,field_id=%d WHERE tab=%s AND field_name=%s',array($tab_id,$field_id,$tab,Utils_RecordBrowserCommon::get_field_id($field)));
            unset($fields[$field_id]);
            $update_map_checkpoint->set('fields_'.$tab_id,$fields);
        }
        
        unset($tabs[$tab_id]);
        $update_map_checkpoint->set('tabs',$tabs);
    }
    $update_map_checkpoint->done();
}

DB::Execute('DELETE FROM recordbrowser_words_map WHERE field_id is null');*/

$finalize_checkpoint = Patch::checkpoint('finalize');
if(!$finalize_checkpoint->is_done()) {
    Patch::require_time(20);

    PatchUtil::db_drop_column('recordbrowser_words_map', 'tab');
    PatchUtil::db_drop_column('recordbrowser_words_map', 'field_name');

    if(DATABASE_DRIVER=='postgres') {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD CONSTRAINT word_id_fk FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index');
    } else {
        DB::Execute('ALTER TABLE recordbrowser_words_map ADD FOREIGN KEY (word_id) REFERENCES recordbrowser_words_index(id)');
    }
    DB::CreateIndex('recordbrowser_words_map__idx','recordbrowser_words_map','word_id,tab_id');
    $finalize_checkpoint->done();
}
