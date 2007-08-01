<?php
/**
 * TestsInit_0 class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-tests
 * @subpackage tests-installer
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class TestsInit_0 extends ModuleInit {
	public static function requires() {
		return array(
		    array('name'=>'Tests/BookmarkBrowser','version'=>0),
		    array('name'=>'Tests/Callbacks','version'=>0),
		    array('name'=>'Tests/Comment','version'=>0),
		    array('name'=>'Tests/FPDF','version'=>0),
		    array('name'=>'Tests/GenericBrowser','version'=>0),
		    array('name'=>'Tests/Image','version'=>0),
		    array('name'=>'Tests/Lang','version'=>0),
		    array('name'=>'Tests/Lightbox','version'=>0),
		    array('name'=>'Tests/Menu','version'=>0),
		    array('name'=>'Tests/Search','version'=>0),
		    array('name'=>'Tests/SharedUniqueHref','version'=>0),
		    array('name'=>'Tests/TabbedBrowser','version'=>0),
		    array('name'=>'Tests/Tooltip','version'=>0),
		    array('name'=>'Tests/Wizard','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
