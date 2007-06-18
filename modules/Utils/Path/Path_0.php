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
	private $_string = '';
	
	public function construct( $title, $address ) {
		$this->_id = Utils_Path::$path_counter;
		$this->layout = $arg;
		if( $title ) {
			$this->_string = '<a "'.($address).'" class=path_link>' . htmlspecialchars($title) . '</a>';
		}
		load_js("modules/Utils/Path/js/path.js");
	}
	public function set_title($title, $children = null) {
		$this->_string .= ' <span class=path_link id=\'path_link_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')"  onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">' . ($title) . '</span>';
		if( is_array($children) ) {
			$this->_string .= '<div class=path_submenu id=\'path_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')" onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">';
			foreach($children as $l) {
				$this->_string .= $l;
			}
			$this->_string .= '</div>';
		}
		$this->_sub++;
	}
	
	public function add_node( $title, $children = null ) {
		$this->_string .= ' &gt; <span class=path_link id=\'path_link_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')"  onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">' . ($title) . '</span>';
		if( is_array($children) ) {
			$this->_string .= '<div class=path_submenu id=\'path_'.$this->_id.'_'.$this->_sub.'\' onmouseover="show_path_children(\''.$this->_id.'_'.$this->_sub.'\')" onmouseout="hide_path_children(\''.$this->_id.'_'.$this->_sub.'\')">';
			foreach($children as $l) {
				$this->_string .= $l;
			}
			$this->_string .= '</div>';
		}
		$this->_sub++;
	}
		
	public function toHtml() {
		Base_ThemeCommon::load_css('Utils/Path');
		eval_js('wait_while_null( "utils_path_writeOut", "utils_path_writeOut('.$this->_id.')" );');
		return '<div id=path_conteiner_'.$this->_id.'>'.$this->_string.'</div>';
	}
	
	public function body() {
		$theme = & $this->init_module('Base/Theme');
		$str = '<div id=path_conteiner_'.$this->_id.'>'.$this->_string.'</div>';
		$theme->assign('path', $str);
		
		eval_js('wait_while_null( "utils_path_writeOut", "utils_path_writeOut('.$this->_id.')" );');
		$theme->display();
	}
}