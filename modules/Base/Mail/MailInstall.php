<?php
/**
 * MailInstall class.
 * 
 * This class provides initialization data for Mail module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MailInstall extends ModuleInstall {
	public function install() {
		$ret = true;
		if($ret) $ret = Variable::set('mail_from_addr','admin@example.com');
		if($ret) $ret = Variable::set('mail_from_name','Administrator');
		if($ret) $ret = Variable::set('mail_method','mail');
		if($ret) $ret = Variable::set('mail_user','');
		if($ret) $ret = Variable::set('mail_password','');
		if($ret) $ret = Variable::set('mail_host','smtp.example.com:25');
		if($ret) $ret = Variable::set('mail_auth',false);
		
		return $ret;
	}
	
	public function uninstall() {
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
	
	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0), 
			array('name'=>'Base/Acl', 'version'=>0), 
			array('name'=>'Base/Admin', 'version'=>0), 
			array('name'=>'Base/Lang', 'version'=>0));
	}
}

?>
