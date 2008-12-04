<?php
/**
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage dashboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_DashboardCommon extends ModuleCommon {
	public static function menu() {
		if(Acl::is_user())
			return array('Dashboard'=>array());
		return array();
	}

	public static function admin_access() {
		return self::Instance()->acl_check('set default dashboard');
	}

	public static function admin_caption() {
		return 'Default dashboard';
	}

	public static function body_access() {
		return Acl::is_user();
	}

	public static function user_settings() {
		$color = array(1 => 'black',
                       2 => 'blue',
                       3 => 'dark-blue',
                       4 => 'dark-gray',
                       5 => 'green',
                       6 => 'dark-green',
                       7 => 'red',
                       8 => 'dark-red',
                       9 => 'yellow',
                      10 => 'dark-yellow');
		return array('Manage dashboard tabs'=>'tabs_list',
				'Misc'=>array(
					array('name'=>'default_color','label'=>'Default dashboard applet color', 'type'=>'select', 'values'=>$color, 'default'=>'4'),
					array('name'=>'remember_tab','label'=>'Remember last visited dashboard tab', 'type'=>'checkbox', 'default'=>false)
//			array('name'=>'display','label'=>'zAction bar displays','type'=>'select','values'=>array('icons only'=>'icons only','text only'=>'text only','both'=>'both'),'default'=>'both','reload'=>true)
				)
				);
	}

	public static function get_available_colors() {
		static $color = array(0 => '',
                              1 => 'black',
                              2 => 'blue',
                              3 => 'dark-blue',
                              4 => 'dark-gray',
                              5 => 'green',
                              6 => 'dark-green',
                              7 => 'red',
                              8 => 'dark-red',
                              9 => 'yellow',
                             10 => 'dark-yellow');
		$color[0] = $color[Base_User_SettingsCommon::get('Base_Dashboard','default_color')];
		return $color;
	}
}
?>
