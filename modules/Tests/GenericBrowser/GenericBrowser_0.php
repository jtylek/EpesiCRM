<?php
/**
 * Test class.
 * 
 * This class is just my first module, test only.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0
 * @package tcms-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class is just my first module, test only.
 * @package tcms-tests
 * @subpackage generic-browser
 */
class Tests_GenericBrowser extends Module {
	
	public function body($arg) {
 		$m = & $this->init_module('Utils/GenericBrowser',null,'t1');
 		$m->set_table_columns(array(array('name'=>'xxx')));
 		$m->add_row('xxx');
 		$m->add_row('sdasf');
 		$m->add_row('wwww');
 		$m->add_row('asgfs');
 		$m->add_row('test');
 		$m->add_row('search');
 		$m->add_row('search keyword');
 		$m->add_row('ttttesst');
 		$m->add_row('xxx');
 		$this->display_module($m);

 		$m = & $this->init_module('Utils/GenericBrowser',null,'t1');
 		$m->set_table_columns(array(array('name'=>'xxx','search'=>1)));
		$m->get_limit(9);
 		$m->add_row('xxx');
 		$m->add_row('sdasf');
 		$m->add_row('wwww');
 		$m->add_row('asgfs');
 		$m->add_row('test');
 		$m->add_row('search');
 		$m->add_row('search keyword');
 		$m->add_row('ttttesst');
 		$m->add_row('xxx');
 		$this->display_module($m);

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/GenericBrowser/GenericBrowserInstall.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/GenericBrowser/GenericBrowserInit_0.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/GenericBrowser/GenericBrowser_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/GenericBrowser/GenericBrowserCommon_0.php');
	}
}
?>
