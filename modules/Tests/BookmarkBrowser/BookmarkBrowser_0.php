<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BookmarkBrowser extends Module {

	public function body($arg) {
		$bb = & $this->init_module('Utils/BookmarkBrowser', 'employees');
		
		$bb->add_item('First section', 'Item #1', 'B');
		$bb->add_item('Second section', 'Item #1', 'A');
		$bb->add_item('Second section', 'Item #2', 'B');
		$bb->add_item('First section', 'Item #2', 'A - this will be the first element in first section');
		$bb->sortAll(true);
		$bb->expand();
		$this->display_module($bb);
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/BookmarkBrowser/BookmarkBrowserInstall.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/BookmarkBrowser/BookmarkBrowserInit_0.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/BookmarkBrowser/BookmarkBrowser_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/BookmarkBrowser/BookmarkBrowserCommon_0.php');
	}

}

?>