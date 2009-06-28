<?php
/**
 * CRM Common class.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2009, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage common
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CommonCommon extends ModuleCommon {
	public static function user_settings() {
		if(Acl::is_user()) {
			$methods = array('none'=>Base_LangCommon::ts('CRM/Common','None'),
					'callto'=>Base_LangCommon::ts('CRM/Common','Skype and other "callto" protocol applications'))
					+ ModuleManager::call_common_methods('dialer_description');
			return array(
				'Dialing'=>array(
					array('name'=>'method','label'=>'Method', 'type'=>'select', 'values'=>$methods, 'default'=>'none'),
				)
			);
		}
		return array();
	}
	
	public static function get_dial_code($title) {
		$method = Base_User_SettingsCommon::get('CRM_Common','method');
		switch($method) {
			case 'none':
				return $title;
			case 'callto':
				return '<a href="callto:'.$title.'">'.$title.'</a>';
			default:
				$dialer = array($method.'Common','dialer');
				if(is_callable($dialer))
					return call_user_func($dialer,$title);
				return $title;
		}
	}
}
?>
