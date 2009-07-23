<?php
/**
 * Fax abstraction layer module
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Fax
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FaxCommon extends ModuleCommon {
	public static function attachment_getters() {
		return array('Fax'=>array('func'=>'fax_file','icon'=>null));
	}
	
	public static function fax_file($f,$oryg) {
		$tmp = self::Instance()->get_data_dir().$oryg;
		copy($f,$tmp);
		
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM_Fax','send',$tmp);
	}
	
	public static function rpicker_contact_format($e) {
		return CRM_ContactsCommon::contact_format_default($e,true);
	}

	public static function rpicker_company_format($e) {
		return $e['company_name'];
	}

	public static function menu() {
		if(!Acl::is_user()) return array();
		return array('CRM'=>array('__submenu__'=>1,'Fax'=>array()));
	}
	

}

?>