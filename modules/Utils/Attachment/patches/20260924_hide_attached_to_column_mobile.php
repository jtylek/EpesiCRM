<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_display_callback('utils_attachment', 'Attached to', array(
    'Utils_AttachmentCommon',
    'display_attached_to',
));
