<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
//addons table
$fields = array(
    array(
        'name' => _M('Recordset'),
        'type' => 'text',
        'param' => 64,
        'display_callback' => array(
            'CRM_PhoneCallCommon',
            'display_recordset',
        ),
        'QFfield_callback' => array(
            'CRM_PhoneCallCommon',
            'QFfield_recordset',
        ),
        'required' => true,
        'extra' => false,
        'visible' => true,
    ),
);
Utils_RecordBrowserCommon::install_new_recordset('phonecall_related', $fields);
Utils_RecordBrowserCommon::set_caption('phonecall_related', _M('Meeting Related Recordsets'));
Utils_RecordBrowserCommon::register_processing_callback('phonecall_related', array('CRM_PhoneCallCommon', 'processing_related'));
Utils_RecordBrowserCommon::add_access('phonecall_related', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('phonecall_related', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('phonecall_related', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('phonecall_related', 'delete', 'SUPERADMIN');
Utils_RecordBrowserCommon::new_record_field('phonecall', array('name' => _M('Related'), 'type' => 'multiselect', 'param' => '__RECORDSETS__::;CRM_PhoneCallCommon::related_crits', 'QFfield_callback' => array(
    'CRM_PhoneCallCommon',
    'QFfield_related',
), 'extra' => false, 'required' => false, 'visible' => true,));

$rel = Utils_RecordBrowserCommon::get_records('phonecall',array('!related_to'=>''));
foreach($rel as $r) {
    $rr = array();
    foreach($r['related_to'] as $id) $rr[] = 'contact/'.$id;
    Utils_RecordBrowserCommon::update_record('phonecall',$r['id'],array('related'=>$rr));
}
Utils_RecordBrowserCommon::delete_record_field('phonecall','Related to');
