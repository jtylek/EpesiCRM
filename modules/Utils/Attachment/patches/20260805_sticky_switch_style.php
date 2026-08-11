<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_QFfield_callback('utils_attachment', 'Sticky', array(
    'Utils_AttachmentCommon',
    'QFfield_sticky',
));
