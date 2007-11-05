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
		if(Acl::is_user()) return array('Mail accounts'=>'account_manager');
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
		$ret = DB::Execute('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Base_UserCommon::get_my_user_id()));
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
	
	////////////////////////////////////////////////////
	// scan mail dir, etc
	private function _get_mail_dir() {
		$dir = $this->get_data_dir().Base_UserCommon::get_my_user_id().'/';
		$accounts = DB::GetCol('SELECT mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Base_UserCommon::get_my_user_id()));
		foreach($accounts as $account) {
			$acc_dir = $dir.str_replace(array('@','.'),array('__at__','__dot__'),$account).'/';
			if(!file_exists($acc_dir)) { // create user dir
				mkdir($acc_dir);
				file_put_contents($acc_dir.'Inbox.mbox','');
				file_put_contents($acc_dir.'Sent.mbox','');
				file_put_contents($acc_dir.'Trash.mbox','');
			}
		}
		return $dir; 
	}
	
	public static function get_mail_dir() {
		return self::Instance()->_get_mail_dir();
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
		foreach($cont as $f) {
			if($f=='.' || $f=='..') continue;
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
			if(ereg('__at__[a-zA-Z0-9]+__dot__',$c)) {
				$ret = $c.'/Inbox';
				break;
			}
		return $ret;
	}
}

?>