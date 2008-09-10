<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClientCommon extends ModuleCommon {
	public static function user_settings() {
		if(Acl::is_user()) return array('Mail accounts'=>'account_manager','Mail settings'=>array(
					array('name'=>'default_dest_mailbox','label'=>'Messages from epesi users deliver to', 'type'=>'select', 'values'=>array('both'=>'Private message and contact mail', 'mail'=>'Mail only', 'pm'=>'Private message only'), 'default'=>'both'),
			)
		);
		return array();
	}

	public static function account_manager_access() {
		return Acl::is_user();
	}
	
	public static function applet_caption() {
		return "Mail indicator";
	}

	public static function applet_info() {
		return "Checks if there is new mail";
	}

	public static function applet_settings() {
		$ret = DB::Execute('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Acl::get_user()));
		$conf = array(array('type'=>'header','label'=>'Choose accounts'));
		while($row=$ret->FetchRow())
			$conf[] = array('name'=>'account_'.$row['id'], 'label'=>$row['mail'], 'type'=>'checkbox', 'default'=>0);
		if(count($conf)==1)
			return array(array('type'=>'static','label'=>'No accounts configured, go Home->My settings->Mail accounts'));
		return $conf;
	}

	public static function menu() {
		return array('Mail client'=>array());
	}
	
	public static function admin_caption() {
		return 'Mail client settings';
	}

	////////////////////////////////////////////////////
	// scan mail dir, etc
	private function _get_mail_dir($user=null) {
		if(!isset($user)) $user = Acl::get_user();
		$dir = $this->get_data_dir().$user.'/';
		if(!file_exists($dir)) mkdir($dir);
		$accounts = DB::GetCol('SELECT mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array($user));
		foreach($accounts as $account) {
			$acc_dir = $dir.str_replace(array('@','.'),array('__at__','__dot__'),$account).'/';
			if(!file_exists($acc_dir)) { // create user dir
				mkdir($acc_dir);
				mkdir($acc_dir.'Inbox');
				mkdir($acc_dir.'Sent');
				mkdir($acc_dir.'Trash');
				mkdir($acc_dir.'Drafts');
			}
		}

		$acc_dir=$dir.'internal/';
		if(!file_exists($acc_dir)) { // create user dir
			mkdir($acc_dir);
			mkdir($acc_dir.'Inbox');
			mkdir($acc_dir.'Sent');
			mkdir($acc_dir.'Trash');
			mkdir($acc_dir.'Drafts');
		}
		return $dir; 
	}
	
	public static function get_next_msg_id($mailbox) {
		$mailbox = rtrim($mailbox,'/').'/';
		$nid = @file_get_contents($mailbox.'.mid');
		if($nid===false || !is_numeric($nid)) {
			$nid = -1;
			$files = scandir($mailbox);
			foreach($files as $f) {
				if(is_numeric($f) && $nid<$f)
					$nid=$f;
			}
		}
		$nid++;
		file_put_contents($mailbox.'.mid',$nid);
		return $nid;
	}
	
	public static function drop_message($mailbox,$subject,$from,$to,$date,$body,$read=false) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mime.php');

		$mailbox = rtrim($mailbox,'/').'/';

		$msg_id = self::get_next_msg_id($mailbox);
		if($msg_id===false) return false;

		$mime = new Mail_Mime();
		$headers = array();
        $headers['From'] = $from;
        $headers['To'] = $to;
	 	$headers['Subject'] = $subject;
		$headers['Date'] = $date;
		$mime->headers($headers);
		$mime->setHTMLBody($body);
		$mbody = $mime->getMessage();
		file_put_contents($mailbox.$msg_id,$mbody);
		Apps_MailClientCommon::append_msg_to_mailbox_index($mailbox,$msg_id,$subject,$from,$to,$date,strlen($mbody),$read);

		return true;
	}
	
	public static function get_mailbox_dir($mail_address) {
		return Apps_MailClientCommon::get_mail_dir().str_replace(array('@','.'),array('__at__','__dot__'),$mail_address).'/';
	}
	
	public static function get_mail_dir($user=null) {
		return self::Instance()->_get_mail_dir($user);
	}
	
	private function _get_mail_account_structure($mdir) {
		$st = array();
		$cont = scandir($mdir);
		foreach($cont as $f) {
			if($f=='.' || $f=='..') continue;
			$path = $mdir.$f;
			$r = array();
			if(is_dir($path) && is_readable($path) && is_writable($path))
				$st[] = array('name'=>str_replace(array('__at__','__dot__'),array('@','.'),$f),'sub'=>$this->_get_mail_account_structure($path.'/'));
		}
		return $st;
	}
	
	private function _get_mail_dir_structure($mdir=null) {
		if($mdir===null) $mdir = $this->_get_mail_dir();
		$st = array();
		$cont = scandir($mdir);
		$st[] = array('name'=>'internal','label'=>Base_LangCommon::ts('Apps_MailClient','Private messages'),'sub'=>$this->_get_mail_account_structure($mdir.'internal/'));
		foreach($cont as $f) {
			if($f=='.' || $f=='..' | $f=='internal') continue;
			$path = $mdir.$f;
			$r = array();
			if(is_dir($path))
				$st[] = array('name'=>str_replace(array('__at__','__dot__'),array('@','.'),$f),'sub'=>$this->_get_mail_account_structure($path.'/'));
		}
		return $st;
	}

	public static function get_mail_dir_structure() {
		return self::Instance()->_get_mail_dir_structure();
	}
	
	public static function get_default_box() {
		$mdir = self::get_mail_dir();
		$cont = scandir($mdir);
		$ret = null;
		foreach($cont as $c)
			if(ereg('__at__[a-zA-Z0-9]+__dot__',$c) || $c=='internal') {
				$ret = '/'.$c.'/Inbox';
				break;
			}
		return $ret;
	}
	
	public static function build_index($boxpath) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mimeDecode.php');
		$boxpath = rtrim($boxpath,'/').'/';
		$out = @fopen($boxpath.'.idx','w');
		if($out==false) return false;
		$files = scandir($boxpath);
		foreach($files as $f) {
			if(!is_numeric($f)) continue;
			$message = @file_get_contents($boxpath.$f);
			if($message===false) continue;
			$decode = new Mail_mimeDecode($message, "\r\n");
			$structure = $decode->decode();
			if(!isset($structure->headers['from']) || !isset($structure->headers['to']) || !isset($structure->headers['date']))
				continue;
			fputcsv($out, array($f,isset($structure->headers['subject'])?substr($structure->headers['subject'],0,256):'no subject',substr($structure->headers['from'],0,256),substr($structure->headers['to'],0,256),substr($structure->headers['date'],0,64),substr(strlen($message),0,64),'0'));
		}
		fclose($out);
		return true;
	}
	
	public static function mark_all_as_read($box) {
		$box = Apps_MailClientCommon::get_mail_dir().trim($box,'/').'/';
		$in = @fopen($box.'.idx','r');
		if($in==false) return false;
		$ret = array();
		while (($data = fgetcsv($in, 700)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=7) continue;
			$data[6]=1;
			$ret[] = $data;
		}
		fclose($in);

		$out = @fopen($box.'.idx','w');
		if($out==false) return false;
		foreach($ret as $d) {
			fputcsv($out, $d);
		}
		fclose($out);
	}
	
	public static function get_index($box,$dir=null) {
		if(isset($dir))
			$box = Apps_MailClientCommon::get_mailbox_dir(trim($box,'/')).$dir.'/';
		else
			$box = Apps_MailClientCommon::get_mail_dir().trim($box,'/').'/';
		if(!file_exists($box.'.idx')) self::build_index($box);
		$in = @fopen($box.'.idx','r');
		if($in==false) return false;
		$ret = array();
		while (($data = fgetcsv($in, 700)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=7) continue;
			$ret[$data[0]] = array('from'=>$data[2], 'to'=>$data[3], 'date'=>$data[4], 'subject'=>$data[1], 'size'=>$data[5],'read'=>$data[6]);
		}
		fclose($in);
		return $ret;
	}
	
	public static function remove_msg($box, $dir, $id) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mimeDecode.php');
		
		if(!self::remove_msg_from_index($box,$dir,$id)) return false;

		$boxpath = self::get_mailbox_dir(trim($box,'/')).$dir;
		@unlink($boxpath.'/'.$id);

		return true;
	}
	
	public static function remove_msg_from_index($box,$dir,$id) {
		$idx = self::get_index($box,$dir);
		
		if($idx===false || !isset($idx[$id])) return false;
		unset($idx[$id]);

		$box = Apps_MailClientCommon::get_mailbox_dir(trim($box,'/')).$dir.'/';
		$out = @fopen($box.'.idx','w');
		if($out==false) return false;

		foreach($idx as $id=>$d)
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
		fclose($out);
		return true;
	}
	
	public static function move_msg($box, $dir, $box2, $dir2, $id) {
		$boxpath = self::get_mailbox_dir(trim($box,'/')).$dir;
		$boxpath2 = self::get_mailbox_dir(trim($box2,'/')).$dir2;
		$msg = @file_get_contents($boxpath.'/'.$id);
		//trigger_error(print_r($boxpath.'/'.$id,true));
		if($msg===false) return false;

		$id2 = self::get_next_msg_id($boxpath2);
		if($id2===false) return false;

		file_put_contents($boxpath2.'/'.$id2,$msg);
		$idx = self::get_index($box,$dir);
		$idx = $idx[$id];
		if(!self::append_msg_to_index($box2,$dir2,$id2,$idx['subject'],$idx['from'],$idx['to'],$idx['date'],$idx['size'],$idx['read']))
			return false;

		@unlink($boxpath.'/'.$id);
		if(!self::remove_msg_from_index($box,$dir,$id)) return false;
		return $id2;
	}

	public static function append_msg_to_index($mail,$box, $id, $subject, $from, $to, $date, $size,$read=false) {
		$box = self::get_mailbox_dir(trim($mail,'/')).$box.'/';
		$out = @fopen($box.'.idx','a');
		if($out==false) return false;
		fputcsv($out,array($id, substr($subject,0,256), substr($from,0,256), substr($to,0,256), substr($date,0,64), substr($size,0,64),$read?'1':'0'));
		fclose($out);
		return true;
	}

	public static function append_msg_to_mailbox_index($mailbox, $id, $subject, $from, $to, $date, $size, $read=false) {
		$mailbox = rtrim($mailbox,'/').'/';
		$out = @fopen($mailbox.'.idx','a');
		if($out==false) return false;
		fputcsv($out,array($id, substr($subject,0,256), substr($from,0,256), substr($to,0,256), substr($date,0,64), substr($size,0,64),$read?'1':'0'));
		fclose($out);
		return true;
	}
	
	public static function read_msg($mailbox, $id) {
		$idx = self::get_index($mailbox);
		
		if($idx===false || !isset($idx[$id])) return false;
		$idx[$id]['read'] = '1';

		$box = Apps_MailClientCommon::get_mail_dir().trim($mailbox,'/').'/';
		$out = @fopen($box.'.idx','w');
		if($out==false) return false;

		foreach($idx as $id=>$d)
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
		fclose($out);
		return true;
	}
	
	public static function mime_header_decode($string) {
		if(!function_exists('imap_mime_header_decode')) return $string;
	    $array = imap_mime_header_decode($string);
	    $str = "";
	    foreach ($array as $key => $part) {
	        $str .= $part->text;
	    }
	    return $str;
	}

	public static function addressbook_rp_mail($e){
		return CRM_ContactsCommon::contact_format_default($e,true);
	}
}

?>