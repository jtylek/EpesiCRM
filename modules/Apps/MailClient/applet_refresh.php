<?php
if(!isset($_POST['acc_id']) || !is_numeric($_POST['acc_id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();
if(!Acl::is_user()) return;

Apps_MailClientCommon::include_path();

$id = $_POST['acc_id'];
$account = DB::GetRow('SELECT * FROM apps_mailclient_accounts WHERE id=%d AND user_login_id=%d',array($id,Acl::get_user()));
if(!$account) die('No such account');

$host = explode(':',$account['incoming_server']);
if(isset($host[1])) $port=$host[1];
	else $port = null;
$host = $host[0];
$user = $account['login'];
$pass = $account['password'];
$ssl = $account['incoming_ssl'];
$method = $account['incoming_method']!='auto'?$account['incoming_method']:null;
$pop3 = ($account['incoming_protocol']==0);
if($pop3) { //pop3
	$native_support = false;
	if(function_exists('imap_open')) {
		$native_support = true;
		$in = @imap_open('{'.$host.':'.($port?$port:'110').'/pop3'.($ssl?'/ssl/novalidate-cert':'').'}', $user,$pass);
		if(!$in) {
			die('(connect error) '.implode(', ',imap_errors()));
		}

		if ($hdr = imap_check($in)) {
			$msgCount = $hdr->Nmsgs;
		} else {
			die('(fetch error) '.implode(', ',imap_errors()));
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
			die('(connect error) '.$ret->getMessage());
		}

		if(PEAR::isError( $ret= $in->login($user , $pass, $method))) {
			die('(login error) '.$ret->getMessage());
		}

		$l = $in->getListing();
	}

	if($l===false) die('unknown error');
	$box_dir = Apps_MailClientCommon::get_mailbox_dir($id);
	if($box_dir===false) {
		die('invalid mailbox');
	}
	$uidls_file = $box_dir.'.uilds';
	if($account['pop3_leave_msgs_on_server']!=0 && file_exists($uidls_file)) {
		$uidls = array();
		if(($uidls_fp = @fopen($uidls_file,'r'))!==false) {
			while(($data = fgetcsv($uidls_fp,200))!==false) {
				$xxx = count($data);
				if($xxx!=2) continue;
				$uidls[$data[0]] = intval($data[1]);
			}
			fclose($uidls_fp);
		}

		if(!empty($uidls)) {
			$count = count($l);
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
		}

	}
	$num_msgs = count($l);

	if($native_support)
		imap_close($in);
	else
		$in->disconnect();
} else { //imap and internal
	$num_msgs = 0;
	$struct = Apps_MailClientCommon::get_mailbox_structure($id);
	if(isset($struct['Inbox'])) {
		function inbox_sum($arr,$p) {
			global $id;
			$msgs = Apps_MailClientCommon::get_number_of_messages($id,$p);
			$ret = $msgs['unread'];
			foreach($arr as $k=>$a) {
				$ret += inbox_sum($a,$p.$k.'/');
			}
			return $ret;
		}
		$num_msgs = inbox_sum($struct['Inbox'],'Inbox/');
	}
}

print($num_msgs);

error_reporting(0);
?>
