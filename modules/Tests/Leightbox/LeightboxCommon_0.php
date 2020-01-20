<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LeightboxCommon extends ModuleCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'__weight__'=>-10, 'Leightbox page'=>array()));
	}
	public static function TEST($e){
		return $e['id'];	
	}
}

?>
