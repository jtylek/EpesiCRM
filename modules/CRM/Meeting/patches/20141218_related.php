<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
//addons table
$fields = array(
    array(
        'name' => _M('Recordset'),
        'type' => 'text',
        'param' => 64,
        'display_callback' => array(
            'CRM_MeetingCommon',
            'display_recordset',
        ),
        'QFfield_callback' => array(
            'CRM_MeetingCommon',
            'QFfield_recordset',
        ),
        'required' => true,
        'extra' => false,
        'visible' => true,
    ),
);
Utils_RecordBrowserCommon::install_new_recordset('crm_meeting_related', $fields);
Utils_RecordBrowserCommon::set_caption('crm_meeting_related', _M('Meeting Related Recordsets'));
Utils_RecordBrowserCommon::register_processing_callback('crm_meeting_related', array('CRM_MeetingCommon', 'processing_related'));
Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('crm_meeting_related', 'delete', 'SUPERADMIN');
Utils_RecordBrowserCommon::new_record_field('crm_meeting', array('name' => _M('Related'), 'type' => 'multiselect', 'param' => '__RECORDSETS__::;CRM_MeetingCommon::related_crits', 'QFfield_callback' => array(
    'CRM_MeetingCommon',
    'QFfield_related',
), 'extra' => false, 'required' => false, 'visible' => true,));
