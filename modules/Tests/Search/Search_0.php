<?php
/**
 * Test class.
 * 
 * Provides for absolutely nothing yet.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Provides for absolutely nothing yet.
 * @package tcms-tests
 * @subpackage search
 */
class Tests_Search extends Module {
	
	public function body($arg) {
	}
	
	public static function menu() {
	}
	
	public static function search($word){
		return array(('Found word '.$word.'!')=>array());
	}

	public static function advanced_search(){
		print('Searching!...');
	}
}
?>
