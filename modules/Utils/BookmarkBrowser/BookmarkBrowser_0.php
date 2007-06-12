<?php
/**
 * TabbedBrowser class.
 * 
 * This class facilitates grouping page content in different tabs.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class facilitates grouping page content in different tabs.
 * @package tcms-utils
 * @subpackage tabbed-browser
 */
class Utils_BookmarkBrowser extends Module {
	private $_bookmarks = array();
	private $current_bookmark = 0;
	private $_id;
	public static $bmk_counter = 0;
	
	public function construct() {
		$this->_id = Utils_BookmarkBrowser::$bmk_counter;
		Utils_BookmarkBrowser::$bmk_counter++;
	}
	
	function knatsort( &$arrIn ) {
		$key_array = array_keys($arrIn);
		$arrOut = array();
		
		natsort( $key_array );
		foreach ( $key_array as $key=>$value ) {
			$arrOut[$value] = $arrIn[$value];
		}
		$arrIn = $arrOut;
	}
	
	public function add_item($cnt, $title) {
			if(!isset($title))
				$this->_bookmarks[$this->current_bookmark][] = $cnt;
			else
				$this->_bookmarks[$this->current_bookmark][$title] = $cnt;
	}
	public function add_bookmark($cnt) {
		if(! key_exists($cnt, $this->_bookmarks))
			$this->_bookmarks[$cnt] = array();
		$this->current_bookmark = $cnt;
	}
	
	public function sortByBookmark() {
		$this->knatsort($this->_bookmarks);
	}

	public function sortAll($items_by_key = false) {
		$this->knatsort($this->_bookmarks);
		foreach($this->_bookmarks as $k => &$v) {
			if($items_by_key == true)
				$this->knatsort($v);
			else
				natsort($v);
		}
	}
	
	public function expand() {
		$content_id = 'utils_bookmarkbrowser_'.$this->_id;
		eval_js('wait_while_null("utils_bookmarkbrowser_set_content_height", "utils_bookmarkbrowser_set_content_height(\''.$content_id.'\')");');
	}
	
	public function body() {
		
		load_js('modules/Utils/BookmarkBrowser/js/BookmarkBrowser.js');
		$bookmark = array_keys($this->_bookmarks);
		$content_id = 'utils_bookmarkbrowser_'.$this->_id;
		$theme = & $this->init_module('Base/Theme');
		$theme->assign('list', $bookmark);
		$theme->assign('content_id', $content_id);
		$theme->assign('groups', count($bookmark));
		$theme->assign('header_width', 100/count($bookmark));
		$theme->assign('items', $this->_bookmarks);
		
		$theme->display();
	}
}
?>
