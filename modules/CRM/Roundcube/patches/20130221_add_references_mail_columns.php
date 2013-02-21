<?php
Utils_RecordBrowserCommon::new_record_field('rc_mails',
    array(
        'name' => _M('Message ID'),
        'type'=>'text',
        'param'=>128,
        'extra'=>false,
        'visible'=>false,
        'required'=>false,
        'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_hidden')
    )
);

Utils_RecordBrowserCommon::new_record_field('rc_mails',
    array(
        'name' => _M('References'),
        'type'=>'text',
        'param'=>128,
        'extra'=>false,
        'visible'=>false,
        'required'=>false,
        'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_hidden')
    )
);
?>