<?php

$fields = array(
    array(
        'name' => _M('Subject'),
        'type'=>'text',
        'param'=>'256',
        'extra'=>false,
        'visible'=>true,
        'required'=>false,
        'display_callback'=>array('CRM_RoundcubeCommon','display_subject')
    ),
    array(
        'name' => _M('Count'),
        'type'=>'calculated',
        'extra'=>false,
        'visible'=>true,
        'required'=>false,
        'display_callback'=>array('CRM_RoundcubeCommon','display_thread_count'),
        'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_thread_count')
    ),
    array(
        'name' => _M('Contacts'),
        'type'=>'crm_company_contact',
        'param'=>array('field_type'=>'multiselect'),
        'required'=>false,
        'extra'=>false,
        'visible'=>true
    ),
    array(
        'name' => _M('First Date'),
        'type'=>'timestamp',
        'extra'=>false,
        'visible'=>true,
        'required'=>false
    ),
    array(
        'name' => _M('Last Date'),
        'type'=>'timestamp',
        'extra'=>false,
        'visible'=>true,
        'required'=>false
    ),
    array(
        'name' => _M('Attachments'),
        'type'=>'calculated',
        'extra'=>false,
        'visible'=>true,
        'display_callback'=>array('CRM_RoundcubeCommon','display_thread_attachments'),
        'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_thread_attachments')
    )
);
Utils_RecordBrowserCommon::install_new_recordset('rc_mail_threads', $fields);
Utils_RecordBrowserCommon::set_caption('rc_mail_threads', _M('Mail Thread'));

Utils_RecordBrowserCommon::new_record_field('rc_mails',
    array(
        'name' => _M('Thread'),
        'type'=>'select',
        'param'=>'rc_mail_threads::Count',
        'extra'=>false,
        'visible'=>false,
        'required'=>false
    )
);

Utils_RecordBrowserCommon::add_access('rc_mail_threads', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('rc_mail_threads', 'delete', 'ACCESS:employee');

?>