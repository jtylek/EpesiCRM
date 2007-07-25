<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> 
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9 
 * @licence SPL
 * @package epesi-utils 
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Path extends Module {
	public static $path_counter = 0;
	private $_id;
	private $_sub = 0;
	private $root;
	private $list = array();
	
	public function construct( $title, $address ) {
		$this->_id = Utils_Path::$path_counter;
		$this->layout = $arg;
		if( $title ) {
			$this->_string = '<a "'.($address).'" class=path_link>' . htmlspecialchars($title) . '</a>';
		}
		load_js("modules/Utils/Path/js/path.js");
	}
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
		}
		$this->_sub++;
	}
	
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
		}
		$this->_sub++;
	}
		
	private function prepare() {
		if( !is_array($this->root) ) {
			$this->root = array_shift($this->list);
		}
	}
	
	public function toHtml() {
		$this->prepare();
		$theme = & $this->init_module('Base/Theme');
		$str = '<div id="path_conteiner_'.$this->_id.'">'.$this->_string.'</div>';
		$theme->assign('id', 'path_conteiner_'.$this->_id);
		$theme->assign('root', $this->root);
		$theme->assign('list', $this->list);
		
		eval_js('wait_while_null( "utils_path_writeOut", "utils_path_writeOut('.$this->_id.')" );');
		return $this->get_html_of_module($theme);
	}
	
	public function body() {
		print $this->toHtml();
	}
}