<?php
/**
 * TestsInstall class.
 * 
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
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
		return array('Author'=>'<a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a>, <a href="mailto:kslawinski@telaxus.com">Kuba Slawinski</a> and <a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Janusz Tylek</a>)', 'License'=>'MIT', 'Description'=>'Module examples pack');
	}
	
	public static function simple_setup() {
		return false;
	}
	
	public function version() {
		return array('2.0');
	}
	// Every surviving demo module, so installing the "Tests" pack actually
	// gets you the reference material. The pre-2.0 list had this backwards -
	// it pulled in the thin widget demos but omitted RecordBrowser, Bugtrack
	// and Report, i.e. the most useful ones, which had to be installed by
	// hand. The thin ones were dropped in the 2026-09-01 trim (see
	// AI-shared/deliberate-removals.md).
	//
	// Sub-modules (Tests/Callbacks/a, Tests/Calendar/Event,
	// Tests/SharedUniqueHref/a) are deliberately absent - each is pulled in
	// by its own parent's requires(), and listing them here as well would
	// just duplicate that edge.
	public function requires($v) {
		return array(
		    array('name'=>'Tests/Bugtrack','version'=>0),
		    array('name'=>'Tests/Calendar','version'=>0),
		    array('name'=>'Tests/Callbacks','version'=>0),
		    array('name'=>'Tests/Colorpicker','version'=>0),
		    array('name'=>'Tests/GenericBrowser','version'=>0),
		    array('name'=>'Tests/Leightbox','version'=>0),
		    array('name'=>'Tests/QuickForm','version'=>0),
		    array('name'=>'Tests/RecordBrowser','version'=>0),
		    array('name'=>'Tests/Report','version'=>0),
		    array('name'=>'Tests/SharedUniqueHref','version'=>0),
		    array('name'=>'Tests/Tooltip','version'=>0),
		    array('name'=>'Tests/Wizard','version'=>0));
	}
}

?>
