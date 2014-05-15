<?php

PatchUtil::db_add_column('utils_attachment_local','func','C(255)');
PatchUtil::db_add_column('utils_attachment_local','args','C(255)');

DB::Execute('UPDATE utils_attachment_local SET func=(SELECT d.f_func FROM utils_attachment_data_1 d WHERE d.id=attachment),args=(SELECT d.f_args FROM utils_attachment_data_1 d WHERE d.id=attachment)');

Utils_RecordBrowserCommon::delete_record_field('utils_attachment','Func');
Utils_RecordBrowserCommon::delete_record_field('utils_attachment','Args');

Utils_RecordBrowserCommon::wipe_access('utils_attachment');
Utils_RecordBrowserCommon::add_access('utils_attachment', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'));
Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('utils_attachment', 'delete', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('utils_attachment', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('utils_attachment', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customer'=>'USER'),array('date'));
