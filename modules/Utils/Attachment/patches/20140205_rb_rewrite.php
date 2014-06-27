<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

@ini_set('memory_limit','256M');
@ini_set('memory_limit','512M');

Utils_WatchdogCommon::dont_notify();

$rs_checkpoint = Patch::checkpoint('recordset');
if (!$rs_checkpoint->is_done()) {
    Patch::require_time(5);
    
    Utils_RecordBrowserCommon::uninstall_recordset('utils_attachment');
    $fields = array(
    array(
        'name' => _M('Edited on'),
        'type' => 'timestamp',
        'extra'=>false,
        'visible'=>true,
        'required' => false,
        'display_callback'=>array('Utils_AttachmentCommon','display_date'),
        'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_date'),
    ),
    array(
        'name' => _M('Title'),
        'type' => 'text',
        'param' => 255,
        'required' => false, 'extra' => false, 'visible' => true
    ),
    array('name' => _M('Note'),
        'type' => 'long text',
        'required' => false,
        'extra' => false,
        'visible'=>true,
        'display_callback'=>array('Utils_AttachmentCommon','display_note'),
        'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_note'),
    ),
    array('name' => _M('Permission'),
        'type' => 'commondata',
        'required' => true,
        'param' => array('order_by_key' => true, 'CRM/Access'),
        'extra' => false),
    array('name' => _M('Sticky'),
        'type' => 'checkbox',
        'visible' => true,
        'extra' => false),
    array('name' => _M('Crypted'),
        'type' => 'checkbox',
        'extra' => false,
        'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_crypted')),
    array(
        'name' => _M('Func'),
        'type' => 'text',
        'param' => 255,
        'required' => true, 'extra' => false, 'visible' => false
    ),
    array(
        'name' => _M('Args'),
        'type' => 'text',
        'param' => 255,
        'required' => true, 'extra' => false, 'visible' => false
    ),
    );
    Utils_RecordBrowserCommon::install_new_recordset('utils_attachment',$fields);
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'), array('func','args'));
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', array('ACCESS:employee','ACCESS:manager'));
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'add', 'ACCESS:employee');
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customer'=>'USER'),array('edited_on'));
    Utils_RecordBrowserCommon::register_processing_callback('utils_attachment',array('Utils_AttachmentCommon','submit_attachment'));
    Utils_RecordBrowserCommon::set_tpl('utils_attachment', Base_ThemeCommon::get_template_filename('Utils/Attachment', 'View_entry'));

    $ret = DB::CreateTable('utils_attachment_local','
			local C(255) NOTNULL,
			attachment I4 NOTNULL,
			func C(255),
			args C(255)',
        array('constraints'=>', FOREIGN KEY (attachment) REFERENCES utils_attachment_data_1(ID)'));
    if(!$ret){
        print('Unable to create table utils_attachment_link.<br>');
        return false;
    }
    DB::CreateIndex('utils_attachment_local__idx', 'utils_attachment_local', 'local');
    
    $rs_checkpoint->done();
}

//parse old notes
Patch::set_message('Processing notes');
$old_checkpoint = Patch::checkpoint('old');
$map = $old_checkpoint->get('map', array());
if(!$old_checkpoint->is_done()) {
    $us = Acl::get_user();
    if($old_checkpoint->has('links')) {
        $links = $old_checkpoint->get('links');
    } else {
        $links = 0;
    }
    if($old_checkpoint->has('links_qty')) {
        $links_qty = $old_checkpoint->get('links_qty');
    } else {
        $links_qty = DB::GetOne('SELECT count(*) FROM utils_attachment_link');
        $old_checkpoint->set('links_qty',$links_qty);
    }
    while($ret = DB::SelectLimit('SELECT * FROM utils_attachment_link ORDER BY id',1,$links++)) {
        $link=$ret->FetchRow();
        if(!$link) break;

        Patch::set_message('Processing note: '.$links.'/'.$links_qty);
        $old_checkpoint->require_time(2);

        $notes = DB::GetAll('SELECT * FROM utils_attachment_note WHERE attach_id=%d ORDER BY revision',$link['id']);
        $note = array_shift($notes);
        Acl::set_user($note['created_by']);
        $rid = Utils_RecordBrowserCommon::new_record('utils_attachment',array('title'=>$link['title'],'note'=>$note['text'],'permission'=>$link['permission'],'sticky'=>$link['sticky'],'crypted'=>array('crypted'=>$link['crypted']),'func'=>$link['func'],'args'=>$link['args'],'__date'=>$note['created_on'],'local'=>$link['local']));
//    DB::Execute('INSERT INTO utils_attachment_local(local,attachment) VALUES(%s,%d)',array($link['local'],$rid));
        $map[$link['id']] = $rid;
        foreach($notes as $note) {
            Acl::set_user($note['created_by']);
            Utils_RecordBrowserCommon::update_record('utils_attachment',$rid,array('note'=>$note['text'],'__date'=>$note['created_on']));
        }
        Acl::set_user($us);
        
        $old_checkpoint->set('links',$links);
        $old_checkpoint->set('map',$map);
    }
}
$old_checkpoint->done();

Patch::set_message('Updating database');
$delete_old_fk_checkpoint = Patch::checkpoint('delete_old_fk');
if (!$delete_old_fk_checkpoint->is_done()) {
    Patch::require_time(5);

    if(DATABASE_DRIVER=='mysqli' || DATABASE_DRIVER=='mysqlt') {
        $a = DB::GetRow('SHOW CREATE TABLE utils_attachment_file');
        if(preg_match('/CONSTRAINT (.+) FOREIGN KEY .*attach_id/',$a[1],$m))
            DB::Execute('alter table `utils_attachment_file` drop foreign key '.$m[1]);
        $a = @DB::GetRow('SHOW CREATE TABLE crm_import_attach');
        if(preg_match('/CONSTRAINT (.+) FOREIGN KEY .*utils_attachment_link/',$a[1],$m))
            DB::Execute('alter table `crm_import_attach` drop foreign key '.$m[1]);

    //    else trigger_error('Unable to find attach_id fkey.', E_USER_ERROR);
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
        WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name='utils_attachment_file' AND kcu.column_name='attach_id';");
        if($a) {
            DB::Execute('alter table utils_attachment_file drop CONSTRAINT "'.$a.'"');
        }

        $a = @DB::GetOne("SELECT
            tc.constraint_name, tc.table_name, kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name
        FROM
            information_schema.table_constraints AS tc
        JOIN information_schema.key_column_usage AS kcu
          ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage AS ccu
          ON ccu.constraint_name = tc.constraint_name
        WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name='crm_import_attach' AND kcu.column_name='id';");
        if($a) {
            DB::Execute('alter table utils_attachment_file drop CONSTRAINT "'.$a.'"');
        }
    }
    @DB::DropIndex('attach_id','utils_attachment_file');

    $delete_old_fk_checkpoint->done();
}

Patch::set_message('Processing files');
$files_checkpoint = Patch::checkpoint('files');
if(!$files_checkpoint->is_done()) {
    if($files_checkpoint->has('files')) {
        $files = $files_checkpoint->get('files');
    } else {
        $files = 0;
    }
    if($old_checkpoint->has('files_qty')) {
        $files_qty = $old_checkpoint->get('files_qty');
    } else {
        $files_qty = DB::GetOne('SELECT count(*) FROM utils_attachment_file');
        $old_checkpoint->set('files_qty',$files_qty);
    }
    
    while($ret = DB::SelectLimit('SELECT f.id,f.attach_id,l.local FROM utils_attachment_file f INNER JOIN utils_attachment_link l ON l.id=f.attach_id ORDER BY f.id',1,$files++)) {
        $row = $ret->FetchRow();
        if(!$row) break;

        Patch::set_message('Processing file: '.$files.'/'.$files_qty);
        $files_checkpoint->require_time(2);

        $row['aid'] = $map[$row['attach_id']];
        @mkdir(DATA_DIR.'/Utils_Attachment/'.$row['aid']);
        @rename(DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'],DATA_DIR.'/Utils_Attachment/'.$row['aid'].'/'.$row['id']);
        DB::Execute('UPDATE utils_attachment_file SET attach_id=%d WHERE id=%d',array($row['aid'],$row['id']));

        $files_checkpoint->set('files',$files);
    }
    
    $files_checkpoint->done();
}

Patch::set_message('Updating database');
$new_fk_checkpoint = Patch::checkpoint('create_new_fk');
if (!$new_fk_checkpoint->is_done()) {
    Patch::require_time(5);

    if(DATABASE_DRIVER=='mysqli' || DATABASE_DRIVER=='mysqlt') {
        DB::Execute('ALTER TABLE utils_attachment_file ADD FOREIGN KEY (attach_id) REFERENCES utils_attachment_data_1(id)');
    } else {
        DB::Execute('ALTER TABLE utils_attachment_file ADD CONSTRAINT attach_id_fk FOREIGN KEY (attach_id) REFERENCES utils_attachment_data_1');
    }
    $new_fk_checkpoint->done();
}

Patch::set_message('Finishing');
$cleanup_checkpoint = Patch::checkpoint('cleanup');
if (!$cleanup_checkpoint->is_done()) {
    Patch::require_time(3);
    
    DB::DropTable('utils_attachment_note');
    DB::DropTable('utils_attachment_link');

    Utils_RecordBrowserCommon::enable_watchdog('utils_attachment', array('Utils_AttachmentCommon','watchdog_label'));

    $cleanup_checkpoint->done();
}

Utils_WatchdogCommon::dont_notify(false);

