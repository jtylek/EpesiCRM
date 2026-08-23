<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_caption('phonecall_related', _M('Phonecalls Related Recordsets'));

Utils_RecordBrowserCommon::set_QFfield_callback('phonecall', 'Related', array(
    'CRM_PhoneCallCommon',
    'QFfield_related',
));
DB::Execute('UPDATE phonecall_field SET param=%s WHERE field=%s', array('__RECORDSETS__::;CRM_PhoneCallCommon::related_crits', 'Related'));
