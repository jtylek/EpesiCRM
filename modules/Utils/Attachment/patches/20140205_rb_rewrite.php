<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');


Utils_WatchdogCommon::dont_notify();

Utils_RecordBrowserCommon::uninstall_recordset('utils_attachment');
$fields = array(
    array(
        'name' => _M('Date'),
        'type' => 'date',
        'extra'=>false,
        'visible'=>true,
        'required' => false,
        'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_date')
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
Utils_RecordBrowserCommon::add_access('utils_attachment', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customer'=>'USER'),array('date'));
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

//parse old notes
$map = array();
$us = Acl::get_user();
$links = DB::GetAll('SELECT * FROM utils_attachment_link');
foreach($links as $link) {
    $notes = DB::GetAll('SELECT * FROM utils_attachment_note WHERE attach_id=%d ORDER BY revision',$link['id']);
    $note = array_shift($notes);
    Acl::set_user($note['created_by']);
    $rid = Utils_RecordBrowserCommon::new_record('utils_attachment',array('title'=>$link['title'],'note'=>$note['text'],'permission'=>$link['permission'],'sticky'=>$link['sticky'],'crypted'=>array('crypted'=>$link['crypted']),'func'=>$link['func'],'args'=>$link['args'],'date'=>$note['created_on'],'local'=>$link['local']));
//    DB::Execute('INSERT INTO utils_attachment_local(local,attachment) VALUES(%s,%d)',array($link['local'],$rid));
    $map[$link['id']] = $rid;
    foreach($notes as $note) {
        Acl::set_user($note['created_by']);
        Utils_RecordBrowserCommon::update_record('utils_attachment',$rid,array('note'=>$note['text']));
    }
}
Acl::set_user($us);


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

$files = DB::GetAll('SELECT f.id,f.attach_id,l.local FROM utils_attachment_file f INNER JOIN utils_attachment_link l ON l.id=f.attach_id');
foreach($files as $row) {
    $row['aid'] = $map[$row['attach_id']];
    @mkdir(DATA_DIR.'/Utils_Attachment/'.$row['aid']);
    @rename(DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'],DATA_DIR.'/Utils_Attachment/'.$row['aid'].'/'.$row['id']);
    DB::Execute('UPDATE utils_attachment_file SET attach_id=%d WHERE id=%d',array($row['aid'],$row['id']));
}

if(DATABASE_DRIVER=='mysqli' || DATABASE_DRIVER=='mysqlt') {
    DB::Execute('ALTER TABLE utils_attachment_file ADD FOREIGN KEY (attach_id) REFERENCES utils_attachment_data_1(id)');
} else {
    DB::Execute('ALTER TABLE utils_attachment_file ADD CONSTRAINT attach_id_fk FOREIGN KEY (attach_id) REFERENCES utils_attachment_data_1');
}

DB::DropTable('utils_attachment_note');
DB::DropTable('utils_attachment_link');

Utils_RecordBrowserCommon::enable_watchdog('utils_attachment', array('Utils_AttachmentCommon','watchdog_label'));

Utils_WatchdogCommon::dont_notify(false);

