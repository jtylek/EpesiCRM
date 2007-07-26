<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> 
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9
 * @licence SPL 
 * @package epesi-utils 
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tree extends Module {
	private static $_counter = 0;
	private $_id;
	private $menu_string;
	private $_sub = 0;
	private $_selected;
	private $_structure;
	private $_closed = true;
	private $_opened_paths = array();
	
	public function construct() {
		$this->_id = Utils_Tree::$_counter;
		Utils_Tree::$_counter++;
		load_js_inline("modules/Utils/Tree/js/tree.js");
	}
	
	public function set_structure($s) {
		$this->_structure = $s;
	}
	
	private function sort_r( & $t ) {
		ksort( $t );
		foreach( $t as $k => $v ) {
			if(is_array($v['sub']))
				$ret .= $this->print_structure_r($v['sub'], $level + 1);
		}
	}
	
	public function sort( $arg ) {
		if(isset($dir))
			print $this->_structure = $dir;
		
		ksort($this->_structure);
		foreach( $t as $k => $v ) {
			if(is_array($v['sub']))
				$ret .= $this->print_structure_r($v['sub'], $level + 1);
		}
	}
	
	public function print_structure_r($t = array(), $level = 0, $path = '') {
		if(count($t) > 0) {
			$ret = '<div class=utils_tree_submenu id=utils_tree_'.$this->_id.'_'.$this->_sub.'>';
			$this->_sub++;
			foreach( $t as $k => $v ) {
				$ret .= '<div class=utils_tree_node onmouseover=\'utils_tree_hl(this)\' onmouseout=\'utils_tree_rg(this)\'><table><tr>';
				if(count($v['sub']) > 0)
					$ret .= '<td id=utils_tree_opener_'.$this->_id.'_'.($this->_sub).' class=utils_tree_opener_active_closed onclick="tree_node_visibility_toggle('.$this->_id.', '.($this->_sub).')"><img id=utils_tree_opener_img_'.$this->_id.'_'.($this->_sub).' src=modules/Utils/Tree/theme/opener_active_closed.gif></td>';
				else
					$ret .= '<td class=utils_tree_opener_inactive><img src=modules/Utils/Tree/theme/opener_inactive.gif></td>';
				if($v['selected'] == 1)
					$ret .= "<td width=100% class=utils_tree_node_content_selected>".$v['name']."</td>";
				else
					$ret .= "<td width=100% class=utils_tree_node_content>".$v['name']."</td>";
				if($v['visible'] == 1)
					array_push($this->_opened_paths, $path);
				if($v['opened'] == 1 && is_array($v['sub']))
					array_push($this->_opened_paths, $path.'_'.$this->_sub);
					
				$ret .= "</tr></table></div>";
				if(is_array($v['sub'])) {
					$ret .= $this->print_structure_r($v['sub'], $level + 1, $path.'_'.$this->_sub);
				}
			}
			$ret .= "</div>";
			return $ret;
		}
		return '';
	}
		
	public function print_structure($t = array(), $level = 0) {
		$this->_sub = 0;
		$ret = '<div class=utils_tree_root>';
		foreach( $t as $k => $v ) {
			$ret .= '<div id=utils_tree_node_'.$this->_id.' class=utils_tree_node onmouseover=\'utils_tree_hl(this)\' onmouseout=\'utils_tree_rg(this)\'><table><tr>';
			if(count($v['sub']) > 0)
				$ret .= '<td id=utils_tree_opener_'.$this->_id.'_'.($this->_sub).' class=utils_tree_opener_active_closed onclick="tree_node_visibility_toggle('.$this->_id.', '.($this->_sub).')"><img id=utils_tree_opener_img_'.$this->_id.'_'.($this->_sub).' src=modules/Utils/Tree/theme/opener_active_closed.gif></td>';
			else
				$ret .= '<td class=utils_tree_opener_inactive><img src=modules/Utils/Tree/theme/opener_inactive.gif></td>';
			
			if($v['selected'] == 1)
				$ret .= "<td width=100% class=utils_tree_node_content_selected>".$v['name']."</td>";
			else
				$ret .= "<td width=100% class=utils_tree_node_content>".$v['name']."</td>";
			
			if($v['visible'] == 1)
				array_push($this->_opened_paths, $path);
			if($v['opened'] == 1 && is_array($v['sub']))
				array_push($this->_opened_paths, $path.'_'.$this->_sub);
					
			$ret .= "</tr></table></div>";
			if(is_array($v['sub'])) {
				$ret .= $this->print_structure_r($v['sub'], $level + 1, $this->_sub);
			}
		}
		$ret .= "</div>";
		return $ret;
	}
	
	public function open_all() {
		$this->_closed = false;
	}
	
	public function toHtml() {
		$s = $this->print_structure($this->_structure);
		$expand_all = '<div class=utils_tree_expand_all id=tree_expand_all_'.$this->_id.' onclick="utils_tree_expand_all('.$this->_id.','.$this->_sub.')">Expand All</div> ';
		$collapse_all = '<div class=utils_tree_expand_all id=tree_expand_all_'.$this->_id.' onclick="utils_tree_collapse_all('.$this->_id.','.$this->_sub.')">Collapse All</div> ';
		$theme = & $this->init_module('Base/Theme');
		$theme->assign('collapse_all', $collapse_all);
		$theme->assign('expand_all', $expand_all);
		$theme->assign('tree', $s);
		
		eval_js('wait_while_null("utils_tree_reset", "utils_tree_reset('.$this->_id.')");');
		foreach($this->_opened_paths as $path) {
			$path = explode('_', $path);
			$path = '['.join(', ', $path).']';
			eval_js('wait_while_null("utils_tree_open", "utils_tree_open('.$this->_id.', '.$path.')");');
		}
		
		if( $this->_closed == false ) {
			eval_js('wait_while_null("utils_tree_expand_all", "utils_tree_expand_all('.$this->_id.','.$this->_sub.')");');
			//eval_js('utils_tree_expand_all('.$this->_id.','.$this->_sub.');');
		}

		return $this->get_html_of_module($theme,null,'display');
	}
	

	public function body( $dir ) {
		print($this->toHtml());
	}
}