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
			$methods = array('none'=>__('None'),
					'callto'=>__('Skype and other "callto" protocol applications'))
					+ ModuleManager::call_common_methods('dialer_description');
			return array(
				__('Dialing')=>array(
					array('name'=>'method','label'=>__('Dialing Method'), 'type'=>'select', 'values'=>$methods, 'default'=>'none'),
				),
				__('Misc')=>array(
					array('name'=>'default_record_permission','label'=>__('Default Records Permission'),'type'=>'select','default'=>0,'values'=>Utils_CommonDataCommon::get_translated_array('CRM/Access', false))
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

	public static function status_filter($rb) {
		$sts = Utils_CommonDataCommon::get_translated_array('CRM/Status',true);
		$trans = array('__NULL__'=>array(), '__NO_CLOSED__'=>array('!status'=>array(3,4)));
		foreach ($sts as $k=>$v)
			$trans[$k] = array('status'=>$k);
		$rb->set_custom_filter('status',array('type'=>'select','label'=>__('Status'),'args'=>array('__NULL__'=>'['.__('All').']','__NO_CLOSED__'=>'['.__('Not closed').']')+$sts,'trans'=>$trans));
	}

}
?>
