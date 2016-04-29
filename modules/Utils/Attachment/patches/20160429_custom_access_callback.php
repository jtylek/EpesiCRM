<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::register_custom_access_callback('utils_attachment', array('Utils_AttachmentCommon', 'rb_access'));
