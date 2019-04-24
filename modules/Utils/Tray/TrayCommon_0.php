<?php
/*
* @author Georgi Hristov <ghristov@gmx.de>
* @copyright Copyright &copy; 2019, Georgi Hristov
* @license MIT
* @version 2.0
* @package epesi-tray
*/

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TrayCommon extends ModuleCommon {
	public static $tray_cols=array(2=>2,3=>3,4=>4,5=>5,6=>6);
	public static $tray_layout=array('checkered'=>'Checkered','white'=>'White');
	private static $tmp_trays;

	public static function menu() {
		if (Base_AclCommon::check_permission('Dashboard')) {
			if (ModuleManager::check_common_methods('tray')) return array(
					_M('Tray') => array(
							'__icon__' => 'icon.png'
					)
			);
		}
		return array();
	}

	public static function applet_caption() {
		return __('Tray');
	}

	public static function caption() {
		return __('Tray');
	}

	public static function applet_info() {
		return __('Displays overview of pending items.');
	}

	public static function applet_settings() {
		return array(
				array(
						'name' => 'title',
						'label' => __('Title'),
						'type' => 'text',
						'default' => __('Tray'),
						'rule' => array(
								array(
										'message' => __('Field required'),
										'type' => 'required'
								)
						)
				),
				array(
						'name' => 'max_trays',
						'label' => __('Tray Limit'),
						'type' => 'select',
						'values' => array(
								'__NULL__' => __('No Limit'),
								4 => 4,
								6 => 6,
								8 => 8,
								10 => 10
						),
						'default' => 6
				),
				array(
						'name' => 'max_slots',
						'label' => __('Slots Limit'),
						'type' => 'select',
						'values' => array(
								'__NULL__' => __('No Limit'),
								2 => 2,
								3 => 3,
								4 => 4,
								5 => 5,
								6 => 6,
								10 => 10
						),
						'default' => 5
				),
				array(
						'name' => 'hide_empty_slots',
						'label' => __('Hide Empty Slots'),
						'type' => 'checkbox',
						'default' => 1
				)
		);
	}
	
	public static function enable($tab) {
		Utils_RecordBrowserCommon::new_browse_mode_details_callback($tab, 'Utils_Tray', 'tray_tab_browse_details');
	}
	
	public static function disable($tab) {
		Utils_RecordBrowserCommon::delete_browse_mode_details_callback($tab, 'Utils_Tray', 'tray_tab_browse_details');
	}

	public static function get_trays() {
		static $trays;
		static $user;
		if(!isset($trays) || $user!=Acl::get_user()) {
			$user = Acl::get_user();
			$trays = ModuleManager::call_common_methods('tray',false);
		}
		return $trays;
	}

	public static function user_settings() {
		return array(
				__('Tray settings') => array(
						array(
								'name' => 'tray_cols',
								'label' => __('Tray Columns'),
								'type' => 'select',
								'values' => Utils_TrayCommon::$tray_cols,
								'default' => 3
						),
						array(
								'name' => 'tray_layout',
								'label' => __('Tray Layout'),
								'type' => 'select',
								'values' => Utils_TrayCommon::$tray_layout,
								'default' => 'checkered'
						)
				)
		);
	}

	//////////////////////////
	// mobile devices
	public static function mobile_menu() {
		if(!Acl::is_user())
		return array();
		return array(__('Tray')=>array('func'=>'mobile_tray','color'=>'blue'));
	}

	public static function mobile_tray() {
		require_once('modules/Utils/Tray/mobile.php');
	}

	public static function mobile_tray_rb($tab, $crits=array(), $sort = array(), $cols = array()) {
		Utils_RecordBrowserCommon::mobile_rb($tab, $crits, $sort, $cols);
	}
}

?>