<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(DB::GetOne('SELECT 1 FROM utils_attachment_field WHERE field=%s',array('Date'))) {
    Utils_RecordBrowserCommon::new_record_field('utils_attachment',             array(
                'name' => _M('Edited on'),
                'type' => 'timestamp',
                'extra'=>false,
                'visible'=>true,
                'required' => false,
                'display_callback'=>array('Utils_AttachmentCommon','display_date'),
                'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_date'),
                'position'=>'Date'
            )
    );
    DB::Execute('UPDATE utils_attachment_data_1 SET f_edited_on=f_date');
    Utils_RecordBrowserCommon::delete_record_field('utils_attachment','Date');

    Utils_RecordBrowserCommon::wipe_access('utils_attachment');
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'view', 'ACCESS:employee', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', array('ACCESS:employee','ACCESS:manager'));
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'add', 'ACCESS:employee',array(),array('edited_on'));
    Utils_RecordBrowserCommon::add_access('utils_attachment', 'edit', 'ACCESS:employee', array('(permission'=>0, '|:Created_by'=>'USER_ID'),array('edited_on'));
}