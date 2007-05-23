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
class Utils_TabbedBrowser extends Module {
	private $tabs;
	private $c_func;
	private $c_caption;
	private $tag;
	
	public function body($template) {
		$theme = & $this->pack_module('Base/Theme');
		
		$captions = array();
		$page = $this->get_module_variable_or_unique_href_variable('page', 0);
		
		$i = 0;
		foreach($this->tabs as $caption=>$val) {
			$captions[$caption] = '<a '.$this->create_unique_href(array('page'=>$i)).'>'.$caption.'</a>';
			if($page==$i) 
				if (isset($val['func'])){
					ob_start();
					call_user_func_array($val['func'],$val['args']);
					$body = ob_get_contents();
					ob_end_clean();
				} else {
					$body = $val['body'];
				}
			$i++;
		}
		
		$lpage = $this->get_module_variable('last_page', -1);
		
		if($lpage!=$page && is_callable($this->c_func)) {
			call_user_func($this->c_func,$page, $lpage);
			$this->set_module_variable('last_page', $page);
			$this->parent->set_reload(true);
		}
		
		$this->tag = md5($body.$page); 
		
		$theme->assign('selected', $page);
		$theme->assign('captions', $captions);
		$theme->assign('body', $body);
		$theme->display($template);
	}
	
	public function set_change_tab_callback(array $func) {
		$this->c_func = $func;
	}
	
	public function tag() {
		print '<!--page '.$this->tag.'-->';
	}

	public function set_tab($caption, $function) {
		$this->tabs[$caption]['func'] = & $function;
		$args = func_get_args();
		array_shift($args);
		array_shift($args);
		$this->tabs[$caption]['args'] = $args;
	}
	
	public function set_default_tab($i) {
		if($this->isset_module_variable('page')) return;
		if(!isset($i)) $i = count($this->tabs)-1;
		$this->set_module_variable('page',$i);
	}

	public function start_tab($caption) {
		ob_start();
		$this->caption = $caption;
	}

	public function close_tab() {
		$this->tabs[$this->caption]['body'] = ob_get_contents();
		ob_end_clean();		
	}
}
?>
