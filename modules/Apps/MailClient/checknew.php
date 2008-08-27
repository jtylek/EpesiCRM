<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past
if(!isset($_GET['id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();
set_time_limit(0);
$mail_size_limit = Variable::get('max_mail_size');
ini_set("memory_limit",$mail_size_limit+32*1024*1024); // max mail size is

if(!Acl::is_user()) die('Not logged in');

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
require_once('Mail/Mbox.php');
require_once('Mail/mimeDecode.php');

function message($id,$text) {
	echo('<script>parent.Apps_MailClient.progress_bar.set_text(parent.$(\''.$_GET['id'].'progresses\'),\''.$id.'\',\''.Epesi::escapeJS($text,false).'\')</script>');
	flush();
	flush();
}

function rm_lock($lock) {
	@unlink(dirname(dirname(dirname(__FILE__))).'/'.$lock);
}

function profiler() {
        static $m = 0;
        if(($mem = memory_get_usage()) > $m) {
		$m = $mem;
		error_log(filesize_hr($m)."\n\n",3,'data/logsze');
	}
}
   
register_tick_function('profiler');
declare(ticks = 10);
   
$accounts = DB::GetAll('SELECT * FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Acl::get_user()));
foreach($accounts as $account) {
	$pop3 = ($account['incoming_protocol']==0);
	if(!$pop3) continue;
	echo('<script>parent.Apps_MailClient.progress_bar.set_text(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\',\''.Epesi::escapeJS($account['mail'],false).'\');');
	echo('parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', 0)</script>');
}
flush();
flush();
foreach($accounts as $account) {
	$host = explode(':',$account['incoming_server']);
	if(isset($host[1])) $port=$host[1];
		else $port = null;
	$host = $host[0];
	$user = $account['login'];
	$pass = $account['password'];
	$ssl = $account['incoming_ssl'];
	$method = $account['incoming_method']!='auto'?$account['incoming_method']:null;
	$pop3 = ($account['incoming_protocol']==0);
	if(!$pop3) continue;
	
	$box = Apps_MailClientCommon::get_mailbox_dir($account['mail']).'/Inbox';
	
	//check if mbox is not locked
	$lock = $box.'.lock';
	if(file_exists($lock)) {
		message($account['id'],$account['mail'].': mailbox locked');
		continue;	
	}
	touch($lock);
	register_shutdown_function('rm_lock',$lock); //be sure that lock was deleted
	
	//open mbox
	$mbox = new Mail_Mbox($box.'.mbox');
	if(($ret = $mbox->setTmpDir('data/Apps_MailClient/tmp'))===false 
		|| ($ret = $mbox->open())===false) {
		message($account['id'],$account['mail'].': unable to open Inbox file');
		unlink($lock);
		continue;	
	}

	message($account['id'],$account['mail'].': login');

	$native_support = false;
	if(function_exists('imap_open')) {
		$native_support = true;
		$in = @imap_open('{'.$host.':'.($port?$port:'110').'/pop3'.($ssl?'/ssl/novalidate-cert':'').'}', $user,$pass);
		if(!$in) {
			message($account['id'],$account['mail'].': (connect error) '.implode(', ',imap_errors()));
			unlink($lock);
			continue;
		}

		message($account['id'],$account['mail'].': fetching');

		if ($hdr = imap_check($in)) {
			$msgCount = $hdr->Nmsgs;
		} else {
			message($account['id'],$account['mail'].': (fetch error) '.implode(', ',imap_errors()));
			unlink($lock);
			continue;			
		}

		
		$l=imap_fetch_overview($in,'1:'.$msgCount,0);
	} else {
		require_once('Net/POP3.php');
		$in = new Net_POP3();

		if($port==null) {
			if($ssl) $port=995;
			else $port=110;
		}
	
		if(PEAR::isError( $ret= $in->connect(($ssl?'ssl://':'').$host , $port) )) {
			message($account['id'],$account['mail'].': (connect error) '.$ret->getMessage());
			unlink($lock);
			continue;
		}

		if(PEAR::isError( $ret= $in->login($user , $pass, $method))) {
			message($account['id'],$account['mail'].': (login error) '.$ret->getMessage());
			unlink($lock);
			continue;
		}

		$l = $in->getListing();
	}
	
	$num = 0;
	$error = false;
	//check uidls and unset already downloaded messages
	$uidls_file = $box.'.uilds';
	$uidls = array();
	if(($uidls_fp = @fopen($uidls_file,'r'))!==false) {
		while(($data = fgetcsv($uidls_fp,200))!==false) {
			$xxx = count($data);
			if($xxx!=2) continue;
			$uidls[$data[0]] = intval($data[1]);
		}
		fclose($uidls_fp);
	}
	$now = time();
	$count = count($l);
	if(!empty($uidls))
		for($k=0; $k<$count; $k++) {
			if($native_support) 
				$uidl = $l[$k]->message_id;
			else
				$uidl = $l[$k]['uidl'];
			if(array_key_exists($uidl,$uidls)) {
				//print('old uidl=>'.$l[$k]['uidl'].', time=>'.$uidls[$l[$k]['uidl']].', live time=>'.($uidls[$l[$k]['uidl']]+$account['pop3_leave_msgs_on_server']*86400).', now=>'.$now.'<br>');
				if($account['pop3_leave_msgs_on_server']>=0 && ($uidls[$uidl]+$account['pop3_leave_msgs_on_server']*86400)<=$now) {
					if($native_support)
						imap_delete($in,$l[$k]->uid);
					else
						$in->deleteMsg($l[$k]['msg_id']);
					unset($uidls[$uidl]);
				}
				unset($l[$k]);
			}
		}
	
		
	$count = count($l);
	$invalid = 0;
	foreach($l as $msgl) {
		message($account['id'],$account['mail'].': getting message '.$num.' of '.$count);
		if($native_support) {
			$size = $msgl->size;
		} else {
			$size= $msgl['size'];
		}
		if($size>$mail_size_limit) {
			$invalid++;
			continue;
		}
		if($native_support)
			$msg = imap_fetchheader($in,$msgl->uid).imap_body($in,$msgl->uid,FT_INTERNAL);
		else
			$msg = $in->getMsg($msgl['msg_id']);
		$decode = new Mail_mimeDecode($msg, "\r\n");
		$structure = $decode->decode();
		if($msg===false || !isset($structure->headers['from']) || !isset($structure->headers['to']) || !isset($structure->headers['date'])) {
			$invalid++;
			//$in->deleteMsg($msgl['msg_id']);
			//$count--;
			continue;
		}
		$msg_id = $mbox->size();
		$mbox->insert("From - ".date('D M d H:i:s Y')."\n".$msg);
		if(!Apps_MailClientCommon::append_msg_to_index($account['mail'],'Inbox',$msg_id,isset($structure->headers['subject'])?$structure->headers['subject']:'no subject',$structure->headers['from'],$structure->headers['to'],$structure->headers['date'],strlen($msg))) {
			message($account['id'],$account['mail'].': broken index file');
			$mbox->remove($msg_id);
			$error = true;
			break;
		}
		$tt = strtotime($structure->headers['date']);
		if($tt===false) $tt=$now;
		//print('uidl=>'.$msgl['uidl'].', time=>'.$tt.', live time=>'.($tt+$account['pop3_leave_msgs_on_server']*86400).', now=>'.$now.'<br>');
		if($account['pop3_leave_msgs_on_server']>=0 && ($tt+$account['pop3_leave_msgs_on_server']*86400)<=$now) {
			if($native_support)
				imap_delete($in,$msgl->uid);
			else
				$in->deleteMsg($msgl['msg_id']);
		} else {
			if($native_support)
				$uidls[$msgl->message_id] = $tt;				
			else
				$uidls[$msgl['uidl']] = $tt;	
		}
		
		$num++;
		echo('<script>parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', '.ceil($num*100/$count).')</script>');
		flush();
		flush();
	}
	
	echo('<script>parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', 100)</script>');
	if(($uidls_fp = @fopen($uidls_file,'w'))!==false) {
		foreach($uidls as $ui=>$t)
			fputcsv($uidls_fp,array($ui,$t));
		fclose($uidls_fp);
	}
	
	if($native_support)
		imap_close($in);
	else
		$in->disconnect();
	
	$mbox->close();
	if(!$error)
		message($account['id'],$account['mail'].': ok, got '.$num.' new messages, '.$invalid.' invalid messages skipped');
	unlink($lock);
}
echo('<script>parent.Apps_MailClient.show_hide_button(\''.$_GET['id'].'\');</script>');
?>
