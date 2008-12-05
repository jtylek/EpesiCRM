<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> 
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 1.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage tree
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tree extends Module {
	private static $_counter = 0;
	private $_id;
	private $menu_string;
	private $_sub = 0;
	private $_selected;
	private $_structure;
	private $_opened = false;
	private $_opened_paths = array();
	
	public function construct() {
		$this->_id = Utils_Tree::$_counter;
		Utils_Tree::$_counter++;
		load_js("modules/Utils/Tree/js/tree.js");
	}
	
	/**
	 * Sets structure of tree. The structure has to be like this:
	 * array(
	 * 	array(
	 * 		'name' => $string, //name_of_branch, basicly any HTML code
	 * 		'opened' => $bool_1, //wheather_branch_is_opened
	 * 		'visible' => $bool_2, //wheather_branch_is_visible (if opened, then also visible)
	 * 		'selected' => $bool_3, //weather item is selected or not
	 * 		'sub' => array( // subbranch of identical structure as parent (leave array empty if you don't want subbranch)
	 * 			...
	 * 		)
	 * 	),
	 * 
	 * 	array(
	 * 		'name' => $string,
	 * 		'opened' => $bool_1,
	 * 		'visible' => $bool_2,
	 * 		'selected' => $bool_3,
	 * 		'sub' => array( // subbranch of identical structure as parent (leave array empty if you don't want subbranch)
	 * 			...
	 * 		)
	 * 	),
	 * 	...
	 * )
	 * 
	 * @param array structure of tree. 
	 */
	public function set_structure($s) {
		$this->_structure = $s;
	}
	
	/**
	 * Method for sorting whole tree structure.
	 */
	public function sort(& $t = null) {
		if($t===null)
			$t = $this->_structure;
		ksort($t);
		foreach( $t as $k => $v ) {
			if(isset($v['sub']) && is_array($v['sub']))
				$this->sort($v['sub']);
		}
	}
	
	private function print_structure($t = array(), $level = 0, $path = null) {
		if(count($t) > 0) {
			if($path===null) {
				$this->_sub = 0;
				$ret = '<div class=utils_tree_root>';
			} else {
				$ret = '<div class=utils_tree_submenu id=utils_tree_'.$this->_id.'_'.$this->_sub.'>';
				$this->_sub++;
			}
			foreach( $t as $k => $v ) {
				$ret .= '<div id=utils_tree_node_'.$this->_id.' class=utils_tree_node onmouseover=\'utils_tree_hl(this)\' onmouseout=\'utils_tree_rg(this)\'><table><tr>';
				if(isset($v['sub']) && count($v['sub']) > 0)
					$ret .= '<td id=utils_tree_opener_'.$this->_id.'_'.($this->_sub).' class=utils_tree_opener_active_closed onclick="tree_node_visibility_toggle('.$this->_id.', '.($this->_sub).')"><img id=utils_tree_opener_img_'.$this->_id.'_'.($this->_sub).' src="'.Base_ThemeCommon::get_template_file($this->get_type(),'opener_active_closed.gif').'"></td>';
				else
				$ret .= '<td class=utils_tree_opener_inactive><img src="'.Base_ThemeCommon::get_template_file($this->get_type(),'opener_inactive.gif').'"></td>';
				if(isset($v['selected']) && $v['selected'] == 1)
					$ret .= "<td width=100% class=utils_tree_node_content_selected>".$v['name']."</td>";
				else
					$ret .= "<td width=100% class=utils_tree_node_content>".$v['name']."</td>";
				if(isset($v['visible']) && $v['visible'] == 1 && $path!==null)
					array_push($this->_opened_paths, $path);
				if(isset($v['opened']) && $v['opened'] == 1 && is_array($v['sub']) && !empty($v['sub']))
					array_push($this->_opened_paths, $path.'_'.$this->_sub);
					
				$ret .= "</tr></table></div>";
				if(isset($v['sub']) && is_array($v['sub'])) {
					$ret .= $this->print_structure($v['sub'], $level + 1, $path.'_'.$this->_sub);
				}
			}
			$ret .= "</div>";
			return $ret;
		}
		return '';
	}
	
	/**
	 * Method for setting every branch opened.
	 * 
	 * @param bool set false if you want to close branches.
	 */
	public function open_all($opened = true) {
		$this->_opened = $opened;
	}
	
	/**
	 * Displays the module.
	 */
	public function body() {
		$s = $this->print_structure($this->_structure);
		$expand_all = '<div class=utils_tree_expand_all id=tree_expand_all_'.$this->_id.' onclick="utils_tree_expand_all('.$this->_id.','.$this->_sub.')">Expand All</div> ';
		$collapse_all = '<div class=utils_tree_expand_all id=tree_expand_all_'.$this->_id.' onclick="utils_tree_collapse_all('.$this->_id.','.$this->_sub.')">Collapse All</div> ';
		$theme = & $this->init_module('Base/Theme');
		$theme->assign('collapse_all', $collapse_all);
		$theme->assign('expand_all', $expand_all);
		$theme->assign('tree', $s);
		
		eval_js('utils_tree_reset('.$this->_id.')');
		foreach($this->_opened_paths as $path) {
			$path = explode('_', $path);
			$path = '['.join(', ', $path).']';
			eval_js('utils_tree_open('.$this->_id.', '.$path.')');
		}
		
		if( $this->_opened == true ) {
			eval_js('utils_tree_expand_all('.$this->_id.','.$this->_sub.')');
			//eval_js('utils_tree_expand_all('.$this->_id.','.$this->_sub.');');
		}

		$theme->display();
	}
}
?>