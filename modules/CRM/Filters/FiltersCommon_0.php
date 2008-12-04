<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage filters
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FiltersCommon extends ModuleCommon {
	
	public static function get_profile_desc() {
		$profile_desc = Module::static_get_module_variable('/Base_Box|0/CRM_Filters|filter','profile_desc','');
		return $profile_desc;
	}
	
	public static function user_settings() {
	if(self::Instance()->acl_check('manage')) return array('Filters'=>'edit');
	return array();
	}

	public static function body_access() {
		return self::Instance()->acl_check('manage');
	}

	public static function get_my_profile() {
		$me = CRM_ContactsCommon::get_my_record();
		return $me['id'];
	}

	public static function get() {
		if(!isset($_SESSION['client']['filter_'.Acl::get_user()]))
			$_SESSION['client']['filter_'.Acl::get_user()] = CRM_FiltersCommon::get_my_profile();
		return '('.$_SESSION['client']['filter_'.Acl::get_user()].')';
	}

	public static function add_action_bar_icon() {
		Base_ActionBarCommon::add('filter','Filters','class="lbOn" rel="crm_filters"');
	}

}

?>
