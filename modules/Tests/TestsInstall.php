<?php
/**
 * TestsInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
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
		return array('Author'=>'<a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a>, <a href="mailto:kslawinski@telaxus.com">Kuba Slawinski</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'MIT', 'Description'=>'Module examples pack');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public function version() {
		return array('1.0');
	}
	public function requires($v) {
		return array(
		    array('name'=>'Tests/BookmarkBrowser','version'=>0),
		    array('name'=>'Tests/Calendar','version'=>0),
		    array('name'=>'Tests/Callbacks','version'=>0),
		    array('name'=>'Tests/Colorpicker','version'=>0),
		    array('name'=>'Tests/Comment','version'=>0),
		    array('name'=>'Tests/FPDF','version'=>0),
		    array('name'=>'Tests/GenericBrowser','version'=>0),
		    array('name'=>'Tests/Image','version'=>0),
		    array('name'=>'Tests/Lang','version'=>0),
		    array('name'=>'Tests/Leightbox','version'=>0),
		    array('name'=>'Tests/Lytebox','version'=>0),
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
