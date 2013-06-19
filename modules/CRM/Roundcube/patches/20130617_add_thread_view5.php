<?php
    	Utils_RecordBrowserCommon::register_processing_callback('rc_mails', array('CRM_RoundcubeCommon', 'submit_mail'));
      $mails = Utils_RecordBrowserCommon::get_records('rc_mails',array('thread'=>null));
      
      foreach($mails as $m) {
        if(preg_match('/\nreferences:(.*)\n/i',$m['headers_data'],$match)) {
            $ref = trim($match[1]);
            Utils_RecordBrowserCommon::update_record('rc_mails',$m['id'],array('references'=>$ref));
        }
        if(preg_match('/\nmessageid:(.*)\n/i',$m['headers_data'],$match)) {
            $mid = str_replace(array('<','>'),'',trim($match[1]));
            Utils_RecordBrowserCommon::update_record('rc_mails',$m['id'],array('message_id'=>$mid));
        }
      }

      foreach($mails as $m)
          CRM_RoundcubeCommon::create_thread($m['id']);
