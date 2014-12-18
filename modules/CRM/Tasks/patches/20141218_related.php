<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
//addons table
$fields = array(
    array(
        'name' => _M('Recordset'),
        'type' => 'text',
        'param' => 64,
        'display_callback' => array(
            'CRM_TasksCommon',
            'display_recordset',
        ),
        'QFfield_callback' => array(
            'CRM_TasksCommon',
            'QFfield_recordset',
        ),
        'required' => true,
        'extra' => false,
        'visible' => true,
    ),
);
Utils_RecordBrowserCommon::install_new_recordset('task_related', $fields);
Utils_RecordBrowserCommon::set_caption('task_related', _M('Meeting Related Recordsets'));
Utils_RecordBrowserCommon::register_processing_callback('task_related', array('CRM_TasksCommon', 'processing_related'));
Utils_RecordBrowserCommon::add_access('task_related', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('task_related', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('task_related', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('task_related', 'delete', 'SUPERADMIN');
Utils_RecordBrowserCommon::new_record_field('task', array('name' => _M('Related'), 'type' => 'multiselect', 'param' => '__RECORDSETS__::;CRM_TasksCommon::related_crits', 'QFfield_callback' => array(
    'CRM_TasksCommon',
    'QFfield_related',
), 'extra' => false, 'required' => false, 'visible' => true,));
