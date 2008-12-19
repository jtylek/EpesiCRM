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
	
	public static function add_help($file,$caption,$c=null) {
		if($file instanceof Module) {
			$file = $file->get_module_dir().'help/'.$caption.'.html';
			if(!isset($c))
				trigger_error('Missing argument 2 for Module::help()');
			$caption = $c;
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