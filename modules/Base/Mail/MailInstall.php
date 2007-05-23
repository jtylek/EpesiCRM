<?php
/**
 * MailInstall class.
 * 
 * This class provides initialization data for Mail module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Mail module.
 * @package tcms-base-extra
 * @subpackage mail
 */
class Base_MailInstall extends ModuleInstall {
	public static function install() {
		$ret = true;
		if($ret) $ret = Variable::set('mail_from_addr','admin@example.com');
		if($ret) $ret = Variable::set('mail_from_name','Administrator');
		if($ret) $ret = Variable::set('mail_method','mail');
		if($ret) $ret = Variable::set('mail_user','');
		if($ret) $ret = Variable::set('mail_password','');
		if($ret) $ret = Variable::set('mail_host','smtp.example.com:25');
		if($ret) $ret = Variable::set('mail_auth','0');
		
		return $ret;
	}
	
	public static function uninstall() {
		$ret = true;
		if($ret) $ret = Variable::delete('mail_from_addr');
		if($ret) $ret = Variable::delete('mail_from_name');
		if($ret) $ret = Variable::delete('mail_method');
		if($ret) $ret = Variable::delete('mail_user');
		if($ret) $ret = Variable::delete('mail_password');
		if($ret) $ret = Variable::delete('mail_host');
		if($ret) $ret = Variable::delete('mail_auth');
		
		return $ret;
	}
}

?>
