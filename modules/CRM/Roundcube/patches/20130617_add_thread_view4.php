<?php
DB::Execute('ALTER TABLE `rc_mails_data_1` CHANGE `f_references` `f_references` varchar(16384) ');
DB::Execute('ALTER TABLE `rc_mails_data_1` CHANGE `f_to` `f_to` varchar(4096) ');

      DB::Execute('UPDATE rc_mails_data_1 SET f_thread=null');
      DB::Execute('DELETE FROM rc_mail_threads_edit_history_data');
      DB::Execute('DELETE FROM rc_mail_threads_edit_history');
      DB::Execute('DELETE FROM rc_mail_threads_data_1');
      DB::Execute('UPDATE rc_mails_data_1 SET f_message_id=REPLACE (f_message_id, "&lt;","")');
      DB::Execute('UPDATE rc_mails_data_1 SET f_message_id=REPLACE (f_message_id, "&gt;","")');
        // create index to optimize counting completed field
        DB::CreateIndex('rc_mails_thread_idx', 'rc_mails_data_1', 'f_thread');
        DB::CreateIndex('rc_mails_msgid_idx', 'rc_mails_data_1', 'f_message_id');

      $mails = Utils_RecordBrowserCommon::get_records('rc_mails');
      
      foreach($mails as $m) {
        if(preg_match('/\nreferences:(.*)\n/i',$m['headers_data'],$match)) {
            $ref = trim($match[1]);
            Utils_RecordBrowserCommon::update_record('rc_mails',$m['id'],array('references'=>$ref));
        }
        if(!$m['message_id'] && preg_match('/\nmessageid:(.*)\n/i',$m['headers_data'],$match)) {
            $mid = str_replace(array('<','>'),'',trim($match[1]));
            Utils_RecordBrowserCommon::update_record('rc_mails',$m['id'],array('message_id'=>$mid));
        }
      }

      foreach($mails as $m)
          CRM_RoundcubeCommon::create_thread($m['id']);
