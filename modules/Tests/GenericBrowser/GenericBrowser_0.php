<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_GenericBrowser extends Module {
	
	public function body() {
 		$m = & $this->init_module('Utils/GenericBrowser',null,'t1');
 		$m->set_table_columns(array(
							  array('name'=>'xxx','width'=>50),
							  array('name'=>'xyz','width'=>50)));
 		$m->add_row('xxx','123');
 		$m->add_row('sdasf','567');
 		$m->add_row('wwww','abc');
 		$m->add_row('asgfs','bla bla');
 		$m->add_row('test','adsad');
 		$m->add_row('search','sjfksdfjdk');
 		$m->add_row('search keyword','test');
 		$m->add_row('ttttesst','djsdkdkdkd kskdk');
 		$m->add_row('xxx','yyyy');
 		$this->display_module($m);

 		$m = & $this->init_module('Utils/GenericBrowser',null,'t2');
 		$m->set_table_columns(array(array('name'=>'xxx','search'=>1)));
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
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/GenericBrowser/GenericBrowser_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/GenericBrowser/GenericBrowserCommon_0.php');
	}
}
?>
