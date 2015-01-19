<?php
$rs_checkpoint = Patch::checkpoint('recordset');
if (!$rs_checkpoint->is_done()) {
    Patch::require_time(5);

        //addons table
        $fields = array(
            array(
                'name'  => _M('Recordset'),
                'type'  => 'text',
                'param' => 64,
                'display_callback' => array(
                    'CRM_RoundcubeCommon',
                    'display_recordset',
                ),
                'QFfield_callback' => array(
                    'CRM_RoundcubeCommon',
                    'QFfield_recordset',
                ),
                'required' => true,
                'extra'    => false,
                'visible'  => true,
            ),
        );
        Utils_RecordBrowserCommon::install_new_recordset('rc_related', $fields);
        Utils_RecordBrowserCommon::set_caption('rc_related', _M('Mail Related Recordsets'));
        Utils_RecordBrowserCommon::register_processing_callback('rc_related', array('CRM_RoundcubeCommon', 'processing_related'));
        Utils_RecordBrowserCommon::add_access('rc_related', 'view', 'ACCESS:employee');
        Utils_RecordBrowserCommon::add_access('rc_related', 'add', 'ADMIN');
        Utils_RecordBrowserCommon::add_access('rc_related', 'edit', 'SUPERADMIN');
        Utils_RecordBrowserCommon::add_access('rc_related', 'delete', 'SUPERADMIN');

        Utils_RecordBrowserCommon::new_record_field('rc_mails',            array(
                'name'     => _M('Related'),
                'type'     => 'multiselect',
                'QFfield_callback' => array(
                    'CRM_RoundcubeCommon',
                    'QFfield_related',
                ),
                'param'    => '__RECORDSETS__::;CRM_RoundcubeCommon::related_crits',
                'extra'    => false,
                'required' => false,
                'visible'  => true,
                'position' => 'Employee'
            ));
            
        Utils_RecordBrowserCommon::new_record('rc_related',array('recordset'=>'company'));
        Utils_RecordBrowserCommon::new_record('rc_related',array('recordset'=>'contact'));

	Utils_RecordBrowserCommon::add_access('rc_mails', 'edit', 'ACCESS:employee',array(),array('subject','employee','date','headers_data','body','from','to','thread','message_id','references'));
        $rs_checkpoint->done();
}

Patch::set_message('Processing addons');
$old_checkpoint = Patch::checkpoint('old');
if(!$old_checkpoint->is_done()) {
    $old = $old_checkpoint->get('old', array());
    if(empty($old) && ModuleManager::is_installed('Premium/RoundcubeCustomAddons')>=0) {
        $old = Utils_RecordBrowserCommon::get_records('premium_roundcube_custom_addon');
        ModuleManager::uninstall('Premium/RoundcubeCustomAddons');
    }
    foreach($old as $i=>$r) {
        if($r['recordset']=='company' || $r['recordset']=='contact') continue;
        $old_checkpoint->require_time(2);
        Utils_RecordBrowserCommon::new_record('rc_related',array('recordset'=>$r['recordset']));
        unset($old[$i]);
        $old_checkpoint->set('old',$old);
    }
    $old_checkpoint->done();
}

Patch::set_message('Processing related');
$related_checkpoint = Patch::checkpoint('related');
if(!$related_checkpoint->is_done()) {
    while(1) {
        $related = $related_checkpoint->get('related', array());
        if(empty($related)) {
            $related = Utils_RecordBrowserCommon::get_records('rc_mails_assoc',array(),array(),array(),10);
            if(empty($related)) break;
        }
        foreach($related as $i=>$r) {
            $related_checkpoint->require_time(5);
            $mail = Utils_RecordBrowserCommon::get_record('rc_mails',$r['mail']);
            $mail['related'][] = $r['recordset'].'/'.$r['record_id'];
            Utils_RecordBrowserCommon::update_record('rc_mails',$r['mail'],array('related'=>$mail['related']));
            Utils_RecordBrowserCommon::delete_record('rc_mails_assoc',$r['id']);
            unset($related[$i]);
            $related_checkpoint->set('related',$related);
        }
    }
    $related_checkpoint->done();
}
Utils_RecordBrowserCommon::uninstall_recordset('rc_mails_assoc');
Utils_RecordBrowserCommon::delete_addon('rc_mails', 'CRM/Roundcube', 'assoc_addon');
