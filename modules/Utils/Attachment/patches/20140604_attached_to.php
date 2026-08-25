<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::new_record_field('utils_attachment', array('name' => _M('Attached to'),
                'type' => 'calculated',
                'extra' => false,
                'display_callback'=>array('Utils_AttachmentCommon','display_attached_to')));
