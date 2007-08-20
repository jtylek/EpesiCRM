<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_GenericBrowser extends Module {
	
	public function body() {
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

 		$m = & $this->init_module('Utils/GenericBrowser',null,'t2');
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
 		$m->add_row('xxxy');
 		$this->display_module($m,array(true),'automatic_display');

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
