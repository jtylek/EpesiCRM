<?php
Utils_RecordBrowserCommon::uninstall_recordset('utils_attachment');
$fields = array(
    array(
        'name' => _M('Local'),
        'type' => 'text',
        'param' => 255,
        'required' => true, 'extra' => false, 'visible' => false
    ),
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
Utils_RecordBrowserCommon::add_access('utils_attachment', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'), array('local','func','args'));
Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('utils_attachment', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('utils_attachment', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customer'=>'USER'),array('date'));
Utils_RecordBrowserCommon::register_processing_callback('utils_attachment',array('Utils_AttachmentCommon','submit_attachment'));

//parse old notes
$map = array();
$us = Acl::get_user();
$links = DB::GetAll('SELECT * FROM utils_attachment_link');
foreach($links as $link) {
    $notes = DB::GetAll('SELECT * FROM utils_attachment_note WHERE attach_id=%d ORDER BY revision',$link['id']);
    $note = array_shift($notes);
    Acl::set_user($note['created_by']);
    $rid = Utils_RecordBrowserCommon::new_record('utils_attachment',array('local'=>$link['local'],'title'=>$link['title'],'note'=>$note['text'],'permission'=>$link['permission'],'sticky'=>$link['sticky'],'crypted'=>$link['crypted'],'func'=>$link['func'],'args'=>$link['args'],'date'=>$note['created_on']));
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
//    else trigger_error('Unable to find attach_id fkey.', E_USER_ERROR);
} else {
    $a = DB::GetRow('\d+ utils_attachment_file');
    if(preg_match('/"(.+)" FOREIGN KEY .*attach_id/',$a[1],$m))
        DB::Execute('alter table utils_attachment_file drop CONSTRAINT "'.$m[1].'"');
}
@DB::DropIndex('attach_id','utils_attachment_file');

$files = DB::GetAssoc('SELECT id,attach_id FROM utils_attachment_file');
foreach($files as $file_id=>$attach_id) {
    //DB::Execute('UPDATE utils_attachent_file SET attach_id=%d WHERE id=%d',$map[$attach_id],$file_id);
}

if(DATABASE_DRIVER=='mysqli' || DATABASE_DRIVER=='mysqlt') {
    DB::Execute('ALTER TABLE utils_attachment_file ADD FOREIGN KEY (attach_id) REFERENCES utils_attachment_data_1(id)');
} else {
    DB::Execute('ALTER TABLE utils_attachment_file ADD CONSTRAINT attach_id_fk FOREIGN KEY (attach_id) REFERENCES utils_attachment_data_1');
}

DB::DropTable('utils_attachment_note');
DB::DropTable('utils_attachment_link');