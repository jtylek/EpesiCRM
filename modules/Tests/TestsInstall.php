<?php
/**
 * TestsInstall class.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage testsinstaller
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class TestsInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:j@epe.si">Janusz Tylek</a>, <a href="mailto:j@epe.si">Janusz Tylek</a> and <a href="mailto:j@epe.si">Arkadiusz Bisaga</a> (<a href="https://epe.si">Janusz Tylek</a>)', 'License'=>'MIT', 'Description'=>'Module examples pack');
	}
	
	public static function simple_setup() {
		return false;
	}
	
	public function version() {
		return array('1.0');
	}
	public function requires($v) {
		return array(
		    array('name'=>'Tests/Calendar','version'=>0),
		    array('name'=>'Tests/Callbacks','version'=>0),
		    array('name'=>'Tests/Colorpicker','version'=>0),
		    array('name'=>'Tests/Comment','version'=>0),
		    array('name'=>'Tests/GenericBrowser','version'=>0),
		    array('name'=>'Tests/Image','version'=>0),
		    array('name'=>'Tests/Lang','version'=>0),
		    array('name'=>'Tests/Leightbox','version'=>0),
		    array('name'=>'Tests/QuickForm','version'=>0),
		    array('name'=>'Tests/Menu','version'=>0),
//		    array('name'=>'Tests/OpenFlashChart','version'=>0),
		    array('name'=>'Tests/Search','version'=>0),
		    array('name'=>'Tests/SharedUniqueHref','version'=>0),
		    array('name'=>'Tests/TabbedBrowser','version'=>0),
		    array('name'=>'Tests/Tooltip','version'=>0),
		    array('name'=>'Tests/Wizard','version'=>0));
	}
}

?>
