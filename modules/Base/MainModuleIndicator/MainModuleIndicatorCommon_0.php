<?php
/**
 * MainModuleIndicatorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage MainModuleIndicator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MainModuleIndicatorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Module indicator settings";
	}
	
	public static function add_help($caption,$file,$c=null) {
		if($caption instanceof Module) {
			if(!isset($c))
				trigger_error('Missing argument 2 for Module::help()');
			$cap = $file;
			$file = $caption->get_module_dir().'help/'.$c.'.html';
			$caption = $cap;
		}
		$_SESSION['client']['help'][$caption] = $file;
	}

	public static function clean_help() {
		$_SESSION['client']['help'] = array();
	}
}

Module::register_method('help',array('Base_MainModuleIndicatorCommon','add_help')); //interactive ts
on_init(array('Base_MainModuleIndicatorCommon','clean_help'));
?>