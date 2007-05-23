<?php
/**
 * MainModuleIndicator class.
 * 
 * This class provides MainModuleIndicator sending functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides MainModuleIndicator sending functionality.
 * @package tcms-base-extra
 * @subpackage MainModuleIndicator
 */
class Base_MainModuleIndicator extends Module {

	public function body($arg) {
		$box_module = ModuleManager::get_instance('Base_Box');
		if($box_module)
			$active_module = $box_module->get_main_module();
		if($active_module && is_callable(array($active_module,'caption'))) {
			$t = & $this->pack_module('Base/Theme');
			$t->assign('text', $active_module->caption());
			$t->display();
		}
	}
	
}
?>
