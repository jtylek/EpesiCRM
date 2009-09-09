<?php
/**
 * TabbedBrowser class.
 * 
 * This class facilitates grouping page content in different tabs.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage tabbed-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TabbedBrowser extends Module {
	private $tabs = array();
	private $c_caption;
	private $tag;
	private $page;
	
	public function construct() {
		if ($this->isset_unique_href_variable('page') || !$this->get_module_variable('force'))
			$this->page = $this->get_module_variable_or_unique_href_variable('page', 0);
		else {
			$this->page = $this->get_module_variable('page', 0);
			$this->unset_module_variable('force');
		}	
		$lpage = $this->get_module_variable('last_page', -1);
	}
	
	/**
	 * Displays tabs.
	 * You can alternatively choose to use different template file for tabs display.
	 * 
	 * @param string template file that will be used
	 */
	
	public function body($template=null) {
		if (empty($this->tabs)) return;
		$theme = $this->init_module('Base/Theme');
		
		$captions = array();
		
		load_js($this->get_module_dir().'tb_.js');
				
		$i = 0;
		if($this->page>=count($this->tabs)) $this->page=0;
		$max = count($this->tabs);
		$path = escapeJS($this->get_path());
		$body = '';
		foreach($this->tabs as $caption=>$val) {
			if($this->page==$i) $selected = ' class="tabbed_browser_selected"';
				else $selected = ' class="tabbed_browser_unselected"';
			if($val['js'])
				$captions[$caption] = '<a id="'.escapeJS($this->get_path(),true,false).'_c'.$i.'" href="javascript:void(0)" onClick="tabbed_browser_switch('.$i.','.$max.',this,\''.$path.'\')"'.$selected.'>'.$caption.'</a>';
			else
				$captions[$caption] = '<a id="'.escapeJS($this->get_path(),true,false).'_c'.$i.'" href="javascript:void(0)" onClick="tabbed_browser_switch('.$i.','.$max.',this,\''.$path.'\')"'.$selected.' original_action="'.$this->create_unique_href_js(array('page'=>$i)).'">'.$caption.'</a>';
			if($this->page==$i || $val['js']) {
				$body .= '<div id="'.escapeJS($this->get_path(),true,false).'_d'.$i.'" '.($this->page==$i?'':'style="display:none"').'>';
				if (isset($val['func'])){
					ob_start();
					call_user_func_array($val['func'],$val['args']);
					$body .= ob_get_contents();
					ob_end_clean();
				} else {
					$body .= $val['body'];
				}
				$body .= '</div>';
			}
			$i++;
		}
		
		$this->tag = md5($body.$this->page); 
		
		$theme->assign('selected', $this->page);
		$theme->assign('captions', $captions);
		$theme->assign('body', $body);
		$theme->display($template);
	}
	
	/**
	 * Perform operation that guarantee module reloading.
	 * You need to call this function from within your module
	 * to make Tabbed Browser work properly.
	 */
	public function tag() {
		print('<span style="display:none">'.$this->tag.'</span>');
	}

	/**
	 * Creates new tab.
	 * You need to specify tab caption and what function should be called.
	 * The rest of the arguments will be passed to the function.
	 * 
	 * @param string tab caption
	 * @param method method that will be called when tab is displayed
	 */
	public function set_tab($caption, $function,$args=array(),$js=false) {
		$this->tabs[$caption]['func'] = & $function;
		$this->tabs[$caption]['args'] = $args;
		$this->tabs[$caption]['js'] = $js;
	}
	
	/**
	 * This method will force Tabbed Browser to switch to selected tab.
	 * 
	 * @param integer tab number
	 */
	public function switch_tab($i) {
		if(!isset($i)) $i = count($this->tabs)-1;
		$this->set_module_variable('page',$i);
		$this->page = $i;
		$this->set_module_variable('force',true);
	}
	
	public function get_tab() {
		if($this->page>=count($this->tabs)) $this->page=0;
		return $this->page;
	}
	
	/**
	 * Sets default tab. 
	 * No action will be done if tabbed browser was already displayed at least once.
	 * 
	 * @param integer tab number
	 */
	public function set_default_tab($i) {
		if($this->isset_module_variable('default_tab')) return;
		if(!isset($i)) $i = count($this->tabs)-1;
		$this->set_module_variable('page',$i);
		$this->set_module_variable('default_tab',true);
		$this->page = $i;
	}

	//always JS
	public function start_tab($caption) {
		ob_start();
		$this->caption = $caption;
	}

	public function end_tab() {
		$this->tabs[$this->caption]['body'] = ob_get_contents();
		ob_end_clean();		
		$this->tabs[$this->caption]['js'] = true;
	}

}
?>
