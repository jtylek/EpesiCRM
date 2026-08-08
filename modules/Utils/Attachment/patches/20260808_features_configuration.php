<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$fields = array(
    array(
        'name'  => _M('Recordset'),
        'type'  => 'text',
        'param' => 64,
        'display_callback' => array('Utils_AttachmentCommon', 'display_recordset'),
        'QFfield_callback' => array('Utils_AttachmentCommon', 'QFfield_recordset'),
        'required' => true,
        'extra'    => false,
        'visible'  => true,
    ),
);
Utils_RecordBrowserCommon::install_new_recordset('utils_attachment_related', $fields);
Utils_RecordBrowserCommon::set_caption('utils_attachment_related', _M('Attachments Related Recordsets'));
Utils_RecordBrowserCommon::register_processing_callback('utils_attachment_related', array('Utils_AttachmentCommon', 'processing_related'));
Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('utils_attachment_related', 'delete', 'SUPERADMIN');
