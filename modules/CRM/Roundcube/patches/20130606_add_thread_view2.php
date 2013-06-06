<?php
$mails = Utils_RecordBrowserCommon::get_records('rc_mails');
foreach($mails as $m)
    Utils_RecordBrowserCommon::update_record('rc_mails',$m['id'],array('message_id'=>ltrim(rtrim($m['message_id'],'>'),'<')));
foreach($mails as $m)
    CRM_RoundcubeCommon::create_thread($m['id']);
?>