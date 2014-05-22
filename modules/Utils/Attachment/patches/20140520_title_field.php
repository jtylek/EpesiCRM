<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE utils_attachment_field SET visible = 0 WHERE field=%s',array('Title'));
Utils_RecordBrowserCommon::set_description_callback('utils_attachment', array('Utils_AttachmentCommon','description_callback'));

