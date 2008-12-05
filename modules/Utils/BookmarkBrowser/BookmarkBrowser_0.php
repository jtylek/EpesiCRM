<?php
/**
 * Utils_BookmarkBrowser.
 * Module for displaying large amounts of data in one table with navigation between specified data groups.
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage bookmark-browser
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');
class Utils_BookmarkBrowser extends Module {
	private $_bookmarks = array();
	private $current_bookmark = 0;
	private $_id;
	public static $bmk_counter = 0;
	
	public function construct() {
		load_js('modules/Utils/BookmarkBrowser/js/BookmarkBrowser.js');
		$this->_id = Utils_BookmarkBrowser::$bmk_counter;
		Utils_BookmarkBrowser::$bmk_counter++;
	}
	
	private function knatsort( &$arrIn ) {
		$key_array = array_keys($arrIn);
		$arrOut = array();
		
		natsort( $key_array );
		foreach ( $key_array as $key=>$value ) {
			$arrOut[$value] = $arrIn[$value];
		}
		$arrIn = $arrOut;
	}
	
	/**
	 * Adds an entry to the browser at given section.
	 * The key may determine order of elements.
	 * 
	 * @param string section id
	 * @param string text that will be displayed
	 * @param string bookmark key, number will be assigned by default
	 */
	 public function add_item($sec, $cnt, $title = null) {
		if(!key_exists($sec, $this->_bookmarks))
			$this->_bookmarks[$sec] = array();
		if(!isset($title))
			$this->_bookmarks[$sec][] = $cnt;
		else
			$this->_bookmarks[$sec][$title] = $cnt;
	}
	
	/**
	 * Sorts sections with natural sort.
	 */
	public function sortSections() {
		$this->knatsort($this->_bookmarks);
	}

	/**
	 * Sorts both sections and entries with natural sort.
	 * 
	 * @param bool weather to sort by values or keys (default is by value)
	 */
	public function sortAll($items_by_key = false) {
		$this->sortSections();
		foreach($this->_bookmarks as $k => &$v) {
			if($items_by_key == true)
				$this->knatsort($v);
			else
				natsort($v);
		}
	}
	
	/**
	 * Expands bookmark browser to the bottom line of the page.
	 */
	public function expand() {
		$content_id = 'utils_bookmarkbrowser_'.$this->_id;
		eval_js('utils_bookmarkbrowser_set_content_height(\''.$content_id.'\')');
	}
	
	/**
	 * Displays bookmark browser.
	 */
	public function body() {
		
		$bookmark = array_keys($this->_bookmarks);
		$content_id = 'utils_bookmarkbrowser_'.$this->_id;
		$theme = & $this->init_module('Base/Theme');
		$theme->assign('list', $bookmark);
		$theme->assign('content_id', $content_id);
		$theme->assign('groups', count($bookmark));
		$theme->assign('header_width', ( count($bookmark) != 0 ? 100/count($bookmark) : 100 ));
		$theme->assign('items', $this->_bookmarks);
		
		$theme->display();
	}
}
?>
