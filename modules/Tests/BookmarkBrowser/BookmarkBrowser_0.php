<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage bookmark-browser
 */
 
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BookmarkBrowser extends Module {

	public function body() {
		$bb = & $this->init_module('Utils/BookmarkBrowser', 'employees');
		
		$bb->add_item('First section', 'Item #1', 'B');
		$bb->add_item('Second section', 'Item #1', 'A');
		$bb->add_item('Second section', 'Item #2', 'B');
		$bb->add_item('First section', 'Item #2', 'A - this will be the first element in first section');
		$bb->sortAll(true);
		$bb->expand();
		$this->display_module($bb);
		
		$t1 = microtime(true);
		for($i=0; $i<1000; $i++)
			$ret = ModuleManager::check_common_methods('menu');
		$t2 = microtime(true);
		for($i=0; $i<1000; $i++) {
			$ret = DB::Execute('SELECT name FROM modules');
//			while($ret->FetchRow());
		}
		$t3 = microtime(true);
		print(($t2-$t1).' > '.($t3-$t2));
		
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/BookmarkBrowser/BookmarkBrowserInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/BookmarkBrowser/BookmarkBrowser_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/BookmarkBrowser/BookmarkBrowserCommon_0.php');
	}

}

?>