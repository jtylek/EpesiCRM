<?php
/**
 * Search class.
 * 
 * Provides for search functionality in a module. 
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage search
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SearchCommon extends ModuleCommon {
	public static function menu() {
		return array('Search'=>array());
	}
}
?>