<?php
/**
 * TabbedBrowser class.
 * 
 * This class facilitates grouping page content in different tabs.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 * @subpackage tabbed-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TabbedBrowser extends Module {
	private $tabs;
	private $c_func;
	private $c_caption;
	private $tag;
	
	/**
	 * Displays tabs.
	 * You can alternatively choose to use different template file for tabs display.
	 * 
	 * @param string template file that will be used
	 */
	public function body($template=null) {
		$theme = & $this->pack_module('Base/Theme');
		
		$captions = array();
		if ($this->get_module_variable('force')) {
			$page = $this->get_module_variable('page', 0);
			$this->unset_module_variable('force');
		} else 	
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
	
	/**
	 * Allows you to set a function 
	 * that will be called each time user switches tabs.
	 * 
	 * This function must accept two arguments:
	 * tab to which user just switched
	 * tab that was displayed when user have chosen to switch
	 * 
	 * @param method method that will be called on switch
	 */
	public function set_change_tab_callback(array $func) {
		$this->c_func = $func;
	}
	
	/**
	 * Perform operation that guarantee module reloading.
	 * You need to call this function from within your module
	 * to make Tabbed Browser work properly.
	 */
	public function tag() {
		print '<!--page '.$this->tag.'-->';
	}

	/**
	 * Creates new tab.
	 * You need to specify tab caption and what function should be called.
	 * The rest of the arguments will be passed to the function.
	 * 
	 * @param string tab caption
	 * @param method method that will be called when tab is displayed
	 */
	public function set_tab($caption, $function) {
		$this->tabs[$caption]['func'] = & $function;
		$args = func_get_args();
		array_shift($args);
		array_shift($args);
		$this->tabs[$caption]['args'] = $args;
	}
	
	/**
	 * This method will force Tabbed Browser to switch to selected tab.
	 * 
	 * @param intereger tab number
	 */
	public function switch_tab($i) {
		if(!isset($i)) $i = count($this->tabs)-1;
		$this->set_module_variable('page',$i);
		$this->set_module_variable('force',true);
	}
	
	/**
	 * Sets default tab. 
	 * No action will be done if tabbed browser was already displayed at least once.
	 * 
	 * @param intereger tab number
	 */
	public function set_default_tab($i) {
		if($this->isset_module_variable('page')) return;
		if(!isset($i)) $i = count($this->tabs)-1;
		$this->set_module_variable('page',$i);
	}

/*	public function start_tab($caption) {
		ob_start();
		$this->caption = $caption;
	}

	public function close_tab() {
		$this->tabs[$this->caption]['body'] = ob_get_contents();
		ob_end_clean();		
	}
*/
}
?>
