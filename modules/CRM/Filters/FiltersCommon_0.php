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
	public static $in_use = false;
	
	public static function get_profile_desc() {
		$profile_desc = Module::static_get_module_variable('/Base_Box|0/CRM_Filters|filter','profile_desc','');
		return $profile_desc;
	}
	
	public static function user_settings() {
		if(Base_AclCommon::check_permission('Manage Perspective')) 
			return array(__('Filters')=>'edit');
		return array();
	}

	public static function body_access() {
		return Base_AclCommon::check_permission('Manage Perspective');
	}

	public static function get_my_profile() {
		$me = CRM_ContactsCommon::get_my_record();
		return $me['id'];
	}

	public static function get() {
		if (isset($_REQUEST['__location'])) self::$in_use = $_REQUEST['__location'];
		else self::$in_use = true;
		if(!isset($_SESSION['client']['filter_'.Acl::get_user()]['value']))
			$_SESSION['client']['filter_'.Acl::get_user()]['value'] = CRM_FiltersCommon::get_my_profile();
		return '('.$_SESSION['client']['filter_'.Acl::get_user()]['value'].')';
	}

	public static function add_action_bar_icon() {
		Base_ActionBarCommon::add('filter',__('Filters'),'class="lbOn" rel="crm_filters"');
	}

	public static function set_profile($prof) {
		if(preg_match('/^c([0-9,]+)$/',$prof,$reqs)) {
			$ret = $reqs[1];
			if(strpos($ret,',')===false)
				$desc = CRM_ContactsCommon::contact_format_no_company($ret,true);
			else
				$desc = __('Custom filter');
		} elseif(is_numeric($prof)) {
			$cids = DB::GetAssoc('SELECT contact_id, contact_id FROM crm_filters_contacts');
			$c = DB::GetCol('SELECT p.contact_id FROM crm_filters_contacts p WHERE p.group_id=%d',array($prof));
			if($c)
				$ret = implode(',',$c);
			else
				$ret = '-1';
			$desc = DB::GetOne('SELECT name FROM crm_filters_group WHERE id=%d',array($prof));
		} elseif($prof=='my') {
			$ret = CRM_FiltersCommon::get_my_profile();
			$desc = __('My records');
		} else {//all and undefined
		$ret = '';
			/*$contacts = Utils_RecordBrowserCommon::get_records('contact', array(), array(), array('last_name'=>'ASC'));
			$contacts_select = array();
			foreach($contacts as $v)
				$contacts_select[] = $v['id'];
			if($contacts_select)
				$ret = implode(',',$contacts_select);
			else
				$ret = '-1';*/

			$desc = __('All records');
		}
//		$this->set_module_variable('profile',$ret);
		$_SESSION['client']['filter_'.Acl::get_user()]['value'] = $ret;
		$_SESSION['client']['filter_'.Acl::get_user()]['desc'] = $desc;
		location(array());
	}
}

?>
