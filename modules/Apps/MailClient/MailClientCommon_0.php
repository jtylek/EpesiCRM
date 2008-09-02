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
				file_put_contents($acc_dir.'Inbox.mbox','');
				file_put_contents($acc_dir.'Sent.mbox','');
				file_put_contents($acc_dir.'Trash.mbox','');
				file_put_contents($acc_dir.'Drafts.mbox','');
			}
		}

		$acc_dir=$dir.'internal/';
		if(!file_exists($acc_dir)) { // create user dir
			mkdir($acc_dir);
			file_put_contents($acc_dir.'Inbox.mbox','');
			file_put_contents($acc_dir.'Sent.mbox','');
			file_put_contents($acc_dir.'Trash.mbox','');
			file_put_contents($acc_dir.'Drafts.mbox','');
		}
		return $dir; 
	}
	
	public static function drop_message($mailbox,$subject,$from,$to,$date,$body) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mime.php');
		require_once('Mail/Mbox.php');

		$mbox = new Mail_Mbox($mailbox.'.mbox');
		if(($ret = $mbox->setTmpDir('data/Apps_MailClient/tmp'))===false 
			|| ($ret = $mbox->open())===false) {
			Epesi::alert($this->lang->ht('Unable to open mailbox folder: '.$save_folder));
			return false;
		}
		$msg_id = $mbox->size();

		$mime = new Mail_Mime();
		$headers = array();
        $headers['From'] = $from;
        $headers['To'] = $to;
	 	$headers['Subject'] = $subject;
		$headers['Date'] = $date;
		$mime->headers($headers);
		$mime->setHTMLBody($body);
		$mbody = $mime->getMessage();
		$mbox->append("From - ".date('D M d H:i:s Y')."\n".$mbody);
		Apps_MailClientCommon::append_msg_to_mailbox_index($mailbox,$msg_id,$subject,$from,$to,$date,strlen($mbody));

		$mbox->close();
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
			if(is_dir($path) && in_array($f.'.mbox',$cont) && is_file($path.'.mbox') && is_readable($path.'.mbox') && is_writable($path.'.mbox'))
				$st[] = array('name'=>str_replace(array('__at__','__dot__'),array('@','.'),$f),'sub'=>$this->_get_mail_account_structure($path.'/'));
			elseif(ereg('^([a-zA-Z0-9]+)\.mbox$',$f,$r) && is_file($path) && is_readable($path) && is_writable($path) && !(in_array($r[1],$cont) && is_dir($mdir.$r[1])))
				$st[] = array('name'=>$r[1]);
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
	
	public static function get_default_mbox() {
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
		require_once('Mail/Mbox.php');
		require_once('Mail/mimeDecode.php');
		$mbox = new Mail_Mbox($boxpath.'.mbox');
		if(($ret = $mbox->setTmpDir(self::Instance()->get_data_dir().'tmp'))===true && ($ret = $mbox->open())===true) {
			$limit_max = $mbox->size();
			$out = @fopen($boxpath.'.idx','w');
			if($out==false) return false;
			for ($n = 0; $n < $limit_max; $n++) {
				if(PEAR::isError($message = $mbox->get($n)))
					continue;
				$decode = new Mail_mimeDecode($message, "\r\n");
				$structure = $decode->decode();
				if(!isset($structure->headers['from']) || !isset($structure->headers['to']) || !isset($structure->headers['date']))
					continue;
				fputcsv($out, array($n,isset($structure->headers['subject'])?substr($structure->headers['subject'],0,256):'no subject',substr($structure->headers['from'],0,256),substr($structure->headers['to'],0,256),substr($structure->headers['date'],0,64),substr(strlen($message),0,64)));
			}
			fclose($out);
			$mbox->close();
			return true;
		}
		return false;
	}
	
	public static function get_index($box,$dir=null) {
		if(isset($dir))
			$box = Apps_MailClientCommon::get_mailbox_dir(trim($box,'/')).$dir;
		else
			$box = Apps_MailClientCommon::get_mail_dir().ltrim($box,'/');
		if(!file_exists($box.'.idx')) self::build_index($box);
		$in = @fopen($box.'.idx','r');
		if($in==false) return false;
		$ret = array();
		while (($data = fgetcsv($in, 660)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=6) continue;
			$ret[$data[0]] = array('from'=>$data[2], 'to'=>$data[3], 'date'=>$data[4], 'subject'=>$data[1], 'size'=>$data[5]);
		}
		fclose($in);
		return $ret;
	}
	
	public static function remove_msg($box, $dir, $id) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/Mbox.php');
		require_once('Mail/mimeDecode.php');
		
		if(!self::remove_msg_from_index($box,$dir,$id)) return false;

		$boxpath = self::get_mailbox_dir(trim($box,'/')).$dir;
		$mbox = new Mail_Mbox($boxpath.'.mbox');
		if(($ret = $mbox->setTmpDir(self::Instance()->get_data_dir().'tmp'))===true && ($ret = $mbox->open())===true) {
			if($mbox->size()<=$id) return false;
			$mbox->remove($id);
		}
		$mbox->close();

		return true;
	}
	
	public static function remove_msg_from_index($box,$dir,$id) {
		$idx = self::get_index($box,$dir);
		
		if($idx===false || !isset($idx[$id])) return false;
		unset($idx[$id]);

		$box = Apps_MailClientCommon::get_mailbox_dir(trim($box,'/')).$dir;
		$out = @fopen($box.'.idx','w');
		if($out==false) return false;
		$idx = array_values($idx);
		foreach($idx as $id=>$d)
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64)));
		fclose($out);
		return true;
	}
	
	public static function move_msg($box, $dir, $box2, $dir2, $id) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/Mbox.php');
		require_once('Mail/mimeDecode.php');
		
		$boxpath = self::get_mailbox_dir(trim($box,'/')).$dir;
		$mbox = new Mail_Mbox($boxpath.'.mbox');
		$boxpath2 = self::get_mailbox_dir(trim($box2,'/')).$dir2;
		$mbox2 = new Mail_Mbox($boxpath2.'.mbox');
		if($mbox->setTmpDir(self::Instance()->get_data_dir().'tmp')===true && $mbox->open()===true &&
			$mbox2->setTmpDir(self::Instance()->get_data_dir().'tmp')===true && $mbox2->open()===true) {
			if($mbox->size()<=$id) return false;
			$id2 = $mbox2->size();
			$msg = $mbox->get($id);
			$mbox2->insert($msg);
			$decode = new Mail_mimeDecode($msg, "\r\n");
			$structure = $decode->decode();
			if(!self::append_msg_to_index($box2,$dir2,$id2,isset($structure->headers['subject'])?$structure->headers['subject']:'no subject',$structure->headers['from'],$structure->headers['to'],$structure->headers['date'],strlen($msg)))
				return false;

			$mbox->remove($id);
			if(!self::remove_msg_from_index($box,$dir,$id)) return false;
			return true;
		}
		return false;
	}

	public static function append_msg_to_index($mail,$box, $id, $subject, $from, $to, $date, $size) {
		$box = self::get_mailbox_dir(trim($mail,'/')).$box;
		$out = @fopen($box.'.idx','a');
		if($out==false) return false;
		fputcsv($out,array($id, substr($subject,0,256), substr($from,0,256), substr($to,0,256), substr($date,0,64), substr($size,0,64)));
		fclose($out);
		return true;
	}

	public static function append_msg_to_mailbox_index($mailbox, $id, $subject, $from, $to, $date, $size) {
		$out = @fopen($mailbox.'.idx','a');
		if($out==false) return false;
		fputcsv($out,array($id, substr($subject,0,256), substr($from,0,256), substr($to,0,256), substr($date,0,64), substr($size,0,64)));
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