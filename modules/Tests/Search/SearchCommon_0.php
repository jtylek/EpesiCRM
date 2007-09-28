<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-tests
 * @subpackage search
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SearchCommon extends ModuleCommon {
	
	public static function search($word){
		return array(('Found word '.$word.'!')=>array());
	}

	public static function advanced_search_access() {
		return true;
	}
}
?>
