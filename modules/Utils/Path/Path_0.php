<?php
/** 
 * Utils_Path
 * Module for creating path like You know from most GTK applications.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com> 
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 1.0 
 * @license MIT
 * @package epesi-utils 
 * @subpackage path
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Path extends Module {
	public static $path_counter = 0;
	private $_id;
	private $_sub = 0;
	private $_string = ''; // TODO: cleanup
	private $root;
	private $list = array();
	
	public function construct( $title=null , $address=null ) {
		$this->_id = Utils_Path::$path_counter;
//		$this->layout = $arg;
		if( $title ) {
			$this->_string = '<a "'.($address).'" class=path_link>' . htmlspecialchars($title) . '</a>';
		}
		load_js("modules/Utils/Path/js/path.js");
	}
	
	/**
	 * Sets title (designated first element) of the path.
	 * 
	 * @param string title. This is pure HTML, so if you want any links, you must spacify them on your own.
	 * @param array submenu with this node's children. Also, it's just displaying the text, so any links must be made on your own.
	 */
	public function set_title($title, $children = null) {
		$this->root = array();
		$this->root['item'] = ' <span class=path_link id=\'path_link_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')"  onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">' . ($title) . '</span>';
		if( is_array($children) ) {
			$this->root['children'] = array();
			$this->root['children_open'] = '<div class=path_submenu id=\'path_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')" onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">';
			foreach($children as $l) {
				$this->root['children'][] = $l;
			}
			$this->root['children_close'] = '</div>';
			$this->root['children_num'] = count($this->root['children']);
		} else
			$this->root['children_num'] = 0;
		$this->_sub++;
	}
	
	/**
	 * Adds an element to the end of the path.
	 * 
	 * @param string title. This is pure HTML, so if you want any links, you must spacify them on your own.
	 * @param array submenu with this node's children. Also, it's just displaying the text, so any links must be made on your own.
	 */
	public function add_node( $title, $children = null ) {
		$this->list[$this->_sub] = array();
		$this->list[$this->_sub]['item'] = '<span class=path_link id=\'path_link_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')"  onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">' . ($title) . '</span>';
		if( is_array($children) ) {
			$this->list[$this->_sub]['children'] = array();
			$this->list[$this->_sub]['children_open'] = '<div class=path_submenu id=\'path_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')" onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">';
			foreach($children as $l) {
				$this->list[$this->_sub]['children'][] = $l;
			}
			$this->list[$this->_sub]['children_close'] = '</div>';
			$this->list[$this->_sub]['children_num'] = count($this->list[$this->_sub]['children']);
		} else
			$this->list[$this->_sub]['children_num'] = 0;
		$this->_sub++;
	}
		
	private function prepare() {
		if( !is_array($this->root) ) {
			$this->root = array_shift($this->list);
		}
	}
	
	/**
	 * Returns HTML code for the path.
	 */
	public function toHtml() {
		$this->prepare();
		$theme = $this->init_module('Base/Theme');
		$str = '<div id="path_conteiner_'.$this->_id.'">'.$this->_string.'</div>';
		$theme->assign('id', 'path_conteiner_'.$this->_id);
		$theme->assign('root', $this->root);
		$theme->assign('list', $this->list);
		
		eval_js('utils_path_writeOut('.$this->_id.')');
		return $this->get_html_of_module($theme,null,'display');
	}
	
	/**
	 * Displays the path.
	 */
	public function body() {
		print $this->toHtml();
	}
}
?>