<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-table'; }

	public static $possible_vals_for_per_page=array(5=>5,10=>10,15=>15,20=>20,25=>25,30=>30,40=>40,50=>50);

	public static function user_settings(){
		return array(__('Browsing tables')=>array(
			array('name'=>'per_page','label'=>__('Records per page'),'type'=>'select','values'=>Utils_GenericBrowserCommon::$possible_vals_for_per_page,'default'=>20),
			array('name'=>'actions_position','label'=>__('Position of \'Actions\' column'),'type'=>'radio','values'=>array(0=>__('Left'),1=>__('Right')),'default'=>0),
			array('name'=>'adv_search','label'=>__('Advanced search by default'),'type'=>'bool','default'=>0),
			array('name'=>'adv_history','label'=>__('Advanced order history'),'type'=>'bool','default'=>0),
			array('name'=>'display_no_records_message','label'=>__('Hide \'No records found\' message'),'type'=>'bool','default'=>0),
			array('name'=>'show_all_button','label'=>__('Display \'Show all\' button'),'type'=>'bool','default'=>1),
			array('name'=>'zoom_actions','label'=>__('Zoom "Actions" buttons'),'type'=>'select', 'values'=>array(0=>__('Never'), 1=>__('For mobile devices'), 2=>__('Always')),'default'=>1),
            array('name'=>'disable_expandable', 'label' => __('Do not use expandable rows'), 'type' => 'bool', 'default' => 0)
			));
	}
	
	public static function hide_overflow_div(){
		eval_js('table_overflow_hide();');
	}

	public static function init_overflow_div(){
		if(!isset($_SESSION['client']['utils_genericbrowser']['div_exists'])) {
			load_js('modules/Utils/GenericBrowser/js/table_overflow.js');
			eval_js('Utils_GenericBrowser__overflow_div();',false);
			$_SESSION['client']['utils_genericbrowser']['div_exists'] = true;
		}
		on_exit(array('Utils_GenericBrowserCommon', 'hide_overflow_div'),null,false);
	}
}

Utils_GenericBrowserCommon::init_overflow_div();

?>
