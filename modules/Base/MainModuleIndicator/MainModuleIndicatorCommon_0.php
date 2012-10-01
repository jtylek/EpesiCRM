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
		return array('label'=>__('Title and logo'), 'section'=>__('Server Configuration'));
	}
	
	public static function add_help($caption,$file,$open=null,$c=null) {
		if($caption instanceof Module) {
			if(!isset($open))
				trigger_error('Missing argument 2 for Module::help()');
			$mod = $caption;
			$caption = $file;
			$file = $mod->get_module_dir().'help/'.$open;
			$open = $c;
		}
		if($open==null) $open=false;
		$_SESSION['client']['help'][$caption] = array($open,$file);
	}

	public static function clean_help() {
		$_SESSION['client']['help'] = array();
	}

	public static function get_href() {
		return 'href="'.self::Instance()->get_module_dir().'help.php?cid='.CID.'" target="_blank"'; // 15:20
	}
	
}

Module::register_method('help',array('Base_MainModuleIndicatorCommon','add_help')); //interactive ts
on_init(array('Base_MainModuleIndicatorCommon','clean_help'));
?>