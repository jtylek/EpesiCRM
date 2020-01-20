<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage TabbedBrowser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_TabbedBrowser extends Module {
	
	public function body() {
		$tb = $this->init_module(Utils_TabbedBrowser::module_name());
		$tb->set_tab('Manage Users', array($this,'xxx'),'users');
		$tb->set_tab('Manage Companies', array($this,'xxx'),'companies');
		$tb->set_tab('Manage Sales Categories', array($this,'xxx'),'categories',true);
		$tb->set_tab('XXX', array($this,'xxx'),'xxx',true);
		$tb->start_tab('XXX');
		print('xXX');
		$tb->end_tab();
		$this->display_module($tb);
//		$tb->tag();

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module(Utils_CatFile::module_name(),'modules/Tests/TabbedBrowser/TabbedBrowserInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module(Utils_CatFile::module_name(),'modules/Tests/TabbedBrowser/TabbedBrowser_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module(Utils_CatFile::module_name(),'modules/Tests/TabbedBrowser/TabbedBrowserCommon_0.php');
	}
	
	public function xxx($q) {
		print($q);
	}
}
?>
