<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past
if(!isset($_GET['id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();
@set_time_limit(0);
$mail_size_limit = Variable::get('max_mail_size');
ini_set("memory_limit",$mail_size_limit+32*1024*1024); // max mail size is

if(!Acl::is_user()) die('Not logged in');

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
require_once('Mail/mimeDecode.php');

function message($id,$text) {
	echo('<script>parent.Apps_MailClient.progress_bar.set_text(parent.$(\''.$_GET['id'].'progresses\'),\''.$id.'\',\''.Epesi::escapeJS($text,false).'\')</script>');
	flush();
	@ob_flush();
}

$accounts = DB::GetAll('SELECT * FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Acl::get_user()));
foreach($accounts as $account) {
	$pop3 = ($account['incoming_protocol']==0);
	if(!$pop3) continue;
	echo('<script>parent.Apps_MailClient.progress_bar.set_text(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\',\''.Epesi::escapeJS($account['mail'],false).'\');');
	echo('parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', 0)</script>');
}
flush();
@ob_flush();
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
	
	$box_root = Apps_MailClientCommon::get_mailbox_dir($account['mail']);
	$box = $box_root.'Inbox';
	
	message($account['id'],$account['mail'].': login');

	$native_support = false;
	if(function_exists('imap_open')) {
		$native_support = true;
		$in = @imap_open('{'.$host.':'.($port?$port:'110').'/pop3'.($ssl?'/ssl/novalidate-cert':'').'}', $user,$pass);
		if(!$in) {
			message($account['id'],$account['mail'].': (connect error) '.implode(', ',imap_errors()));
			continue;
		}

		message($account['id'],$account['mail'].': fetching');

		if ($hdr = imap_check($in)) {
			$msgCount = $hdr->Nmsgs;
		} else {
			message($account['id'],$account['mail'].': (fetch error) '.implode(', ',imap_errors()));
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
			continue;
		}

		if(PEAR::isError( $ret= $in->login($user , $pass, $method))) {
			message($account['id'],$account['mail'].': (login error) '.$ret->getMessage());
			continue;
		}

		$l = $in->getListing();
	}
	
	$num = 0;
	$error = false;
	//check uidls and unset already downloaded messages
	$uidls_file = $box_root.'.uilds';
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
			if($native_support) {
				if(!isset($l[$k]->message_id)) {
					unset($l[$k]);
					continue;
				}
				$uidl = $l[$k]->message_id;
			} else {
				if(!isset($l[$k]['uidl'])) {
					unset($l[$k]);
					continue;
				}
				$uidl = $l[$k]['uidl'];
			}
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
	
	if(($uidls_fp = @fopen($uidls_file,'a'))==false) {
		message($account['id'],$account['mail'].': unable to open UIDLS file');
		continue;
	}
	message($account['id'],$account['mail'].': waiting for mailbox lock.');
	if (!flock($uidls_fp, LOCK_EX)) {
		message($account['id'],$account['mail'].': mailbox locked.');
		continue;	
	}	

	$count = count($l);
	$invalid = 0;
	foreach($l as $msgl) {
		message($account['id'],$account['mail'].': getting message '.$num.' of '.$count);
		if($native_support) {
			if(!isset($msgl->size)) {
				$invalid++;
				continue;
			}
			$size = $msgl->size;
		} else {
			if(!isset($msgl['size'])) {
				$invalid++;
				continue;
			}
			$size= $msgl['size'];
		}
		if($size>$mail_size_limit) {
			$invalid++;
			continue;
		}
		if($native_support) {
			if(!isset($msgl->uid)) {
				$invalid++;
				continue;
			}
			$msg = imap_fetchheader($in,$msgl->uid).imap_body($in,$msgl->uid,FT_INTERNAL);
		} else {
			if(!isset($msgl['msg_id'])) {
				$invalid++;
				continue;
			}
			$msg = $in->getMsg($msgl['msg_id']);
		}
		if($msg===false) {
			$invalid++;
			continue;
		}
		$decode = new Mail_mimeDecode($msg, "\r\n");
		$structure = $decode->decode();
		if(!isset($structure->headers['from']))
			$structure->headers['from'] = '';
		if(!isset($structure->headers['to']))
			$structure->headers['to'] = '';
		if(!isset($structure->headers['date']))
			$structure->headers['date'] = '';
		$msg_id = Apps_MailClientCommon::get_next_msg_id($box);
		file_put_contents($box.'/'.$msg_id,$msg);
		if(!Apps_MailClientCommon::append_msg_to_index($account['mail'],'Inbox',$msg_id,isset($structure->headers['subject'])?$structure->headers['subject']:'no subject',$structure->headers['from'],$structure->headers['to'],$structure->headers['date'],strlen($msg))) {
			message($account['id'],$account['mail'].': broken index file');
			@unlink($box.'/'.$msg_id);
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
			if($native_support) {
				if(!isset($msgl->message_id)) {
					$invalid++;
					continue;
				}
				$msg_uidl = $msgl->message_id;
			} else {
				if(!isset($msgl['uidl'])) {
					$invalid++;
					continue;
				}
				$msg_uidl = $msgl['uidl'];
			}
			fputcsv($uidls_fp,array($msg_uidl,$tt));
		}
		
		$num++;
		echo('<script>parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', '.ceil($num*100/$count).')</script>');
		flush();
		@ob_flush();
	}
	
	echo('<script>parent.Apps_MailClient.progress_bar.set_progress(parent.$(\''.$_GET['id'].'progresses\'),\''.$account['id'].'\', 100)</script>');
	flock($uidls_fp, LOCK_UN);
	fclose($uidls_fp);
	
	if($native_support)
		imap_close($in);
	else
		$in->disconnect();
	
	if(!$error)
		message($account['id'],$account['mail'].': ok, got '.$num.' new messages, '.$invalid.' invalid messages skipped');
}
echo('<script>parent.Apps_MailClient.show_hide_button(\''.$_GET['id'].'\');</script>');

error_reporting(0);
?>
