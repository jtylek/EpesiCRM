<?php
if(!isset($_POST['acc_id']))
	die('Invalid request');

define('CID',false);
require_once('../../../include.php');
session_commit();
ModuleManager::load_modules();
if(!Acl::is_user()) return;

ini_set('include_path',dirname(__FILE__).'/PEAR'.PATH_SEPARATOR.ini_get('include_path'));

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
if($account['mail']==='#internal') {
	//TODO: internal mail
	$num_msgs = 0;
} elseif($pop3) { //pop3
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
	$box_root = str_replace(array('@','.'),array('__at__','__dot__'),$account['mail']);
	$box = $box_root.'/Inbox';
	$uidls_file = Apps_MailClientCommon::get_mail_dir().$box_root.'/.uilds';
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
} else { //imap
	require_once('Net/IMAP.php');
	if($port==null) {
		if($ssl) $port=993;
		else $port=143;
	}

	if(function_exists('imap_open')) {
		$in = @imap_open('{'.$host.':'.$port.'/imap'.($ssl?'/ssl/novalidate-cert':'').'}INBOX', $user,$pass);
		if(!$in) {
			die('(connect error) '.implode(', ',imap_errors()));
		}

		if ($hdr = imap_check($in)) {
			$msgCount = $hdr->Nmsgs;
		} else {
			die('(fetch error) '.implode(', ',imap_errors()));
		}


		$l=imap_fetch_overview($in,'1:'.$msgCount,0);
		$num_msgs = 0;
		foreach($l as $v) {
			if(!$v->seen && !$v->deleted) $num_msgs++;
		}
	} else {
		$in = new Net_IMAP();

		if(PEAR::isError( $ret= $in->connect(($ssl?'ssl://':'').$host , $port) ))
			die('(connect error) '.$ret->getMessage());

		if(PEAR::isError( $ret= $in->login($user , $pass, $method)))
			die('(login error) '.$ret->getMessage());

		if(PEAR::isError($num_msgs = $in->getNumberOfUnSeenMessages()))
			die('(connection error) '.$num_msgs->getMessage());

		$in->disconnect();
	}
}

print($num_msgs);

error_reporting(0);
?>
