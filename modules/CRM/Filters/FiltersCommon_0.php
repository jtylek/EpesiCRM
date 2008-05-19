<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-Filters
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FiltersCommon extends ModuleCommon {
    public static function user_settings() {
	if(self::Instance()->acl_check('manage')) return array('Filters'=>'edit');
	return array();
    }

    public static function body_access() {
        return self::Instance()->acl_check('manage');
    }

	public static function get_my_profile() {
		$me = CRM_ContactsCommon::get_contacts(array('login'=>Acl::get_user()),array('id'));
		$ret = array();
		foreach($me as $v)
			$ret[] = $v['id'];
		return implode(',',$ret);
	}
	
   public static function get() {
	if(!isset($_SESSION['client']['filter']))
		$_SESSION['client']['filter'] = CRM_FiltersCommon::get_my_profile();
	return '('.$_SESSION['client']['filter'].')';
   }

}

?>
