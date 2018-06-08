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
	private $max;
	
	public function construct() {
		if ($this->isset_unique_href_variable('page') || !$this->get_module_variable('force'))
			$this->page = $this->get_module_variable_or_unique_href_variable('page', 0);
		else {
			$this->page = $this->get_module_variable('page', 0);
			$this->unset_module_variable('force');
		}
		if (is_numeric($this->page)) {
			$this->page = (int)$this->page;
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
		load_js($this->get_module_dir().'tb_.js');
				
		$i = 0;
		$this->check_valid_page();
		$this->max = count($this->tabs);
		$body = '';
		$submenus = array();
		foreach($this->tabs as $caption=>$val) {
			if (substr_count($caption, '#')==1) {
				list($group, $s_caption) = explode('#', $caption);
				if (!isset($submenus[$group]))
					$submenus[$group] = array();
				$submenus[$group][$s_caption] = $val;
				
				$this->tabs[$s_caption] = $val;
				unset($this->tabs[$caption]);
			}
		}
		foreach ($submenus as $group=>$captions) {
			if (count($captions)==1) {
				unset($submenus[$group]);
			} else {
				if (isset($this->tabs[$group])) {
					$submenus[$group][$group] = $this->tabs[$group];
					unset($this->tabs[$group]);
				}
				foreach ($captions as $caption=>$val)
					unset($this->tabs[$caption]);
			}
		}

		$final_captions = array();

		foreach($this->tabs as $caption=>$val) {
			$selected = $this->page === $i || (is_string($this->page) && $this->page == $caption);
			$final_captions[$caption] = $this->get_link($i, $val, $caption);
			if ($selected || $val['js'])
				$body .= $this->display_contents($val, $i, $selected);
			$i++;
		}
		
		$captions_subs = array();
		foreach ($submenus as $group=>$captions) {
			$selected = false;
            $selected_c = ' class="tabbed_browser_unselected"';
			$subs = array();
			foreach ($captions as $caption=>$val) {
				if($this->page === $i || (is_string($this->page) && $this->page == $caption)) {
                    $selected_c = ' class="tabbed_browser_selected"';
					$selected = true;
					$group = $group.': '.$caption;
				}
				if($selected || $val['js'])
					$body .= $this->display_contents($val, $i, $selected);
				$subs[] = $this->get_link($i, $val, $caption, $group);
				$i++;
			}
			$final_captions[$group] = '<span id="tabbed_browser_submenu_'.$group.'"'.$selected_c.'>'.$group.'&nbsp;'.'<img src="'.Base_ThemeCommon::get_template_file('Utils_TabbedBrowser','submenu.png').'">'.'</span>';
			$captions_subs[$group] = $subs;
		}
		$this->tag = md5($body.$this->page); 
		$options['selected'] = $this->page;
		$options['captions'] = $final_captions;
		$options['captions_submenus'] = $captions_subs;
		$options['body'] = $body;
        $this->twig_display('default.twig', $options);
        load_css('modules/Utils/TabbedBrowser/theme/default.css');
	}
	
	private function display_contents($val, $i, $selected) {
		$options = array();
		$options['id'] = escapeJS($this->get_path(), true, false) . '_d' . $i;
        $options['current'] = $selected;
        $options['body'] = $this->get_body($val);
        return $this->twig_render('body.twig',$options);
	}
	
	public function tab_icon($caption, $icon=false) {
		$id = $this->get_tab_id($caption).'_icon';
		if ($icon)
			eval_js('var img=jq("#'.$id.'");img.attr("src","'.$icon.'");img.show();');
		else
			eval_js('var img=jq("#'.$id.'");img.hide();');
	}
	
	public function get_tab_id($caption) {
		if (!isset($this->tabs[$caption])) return null;
		return escapeJS($this->get_path(),true,false).'_c'.$this->tabs[$caption]['id'];
	}
	
	private function get_link($i, $val, $caption, $parent=null) {
		if ($parent===null) $parent = '';
		else $parent = ' parent_menu="'.$parent.'"';
		$path = escapeJS($this->get_path());
		if($this->page === $i || (is_string($this->page) && $this->page == $caption)) $selected = true;
			else $selected = false;
		$icon = '<img class="tab_icon" id="'.$this->get_tab_id($caption).'_icon" src="" style="display:none;">';
		if (isset($val['href']) && $val['href'])
			$href = $val['href'];
		elseif ($val['js'])
			$href = 'href="javascript:void(0)" onClick="tabbed_browser_switch('.$i.','.$this->max.',this,\''.$path.'\')"';
		else
			$href = 'href="javascript:void(0)" onClick="tabbed_browser_switch('.$i.','.$this->max.',this,\''.$path.'\')" original_action="'.$this->create_unique_href_js(array('page'=>$i)).'"';

		return array(
			'id' => $this->get_tab_id($caption),
			'selected' => ($this->page == $i),
			'caption' => $caption,
			'parent' => $parent,
			'href' => $href
		);
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
		$this->tabs[$caption]['id'] = count($this->tabs);
		$this->tabs[$caption]['func'] = & $function;
		$this->tabs[$caption]['args'] = $args;
		$this->tabs[$caption]['js'] = $js;
	}
	
	/**
	 * This method will force Tabbed Browser to switch to selected tab.
	 * 
	 * @param integer tab number
	 */
	public function switch_tab($i=null) {
		if(!isset($i)) $i = count($this->tabs)-1;
		$this->set_module_variable('page',$i);
		$this->page = $i;
		$this->set_module_variable('force',true);
		location(array());
	}
	
	public function get_tab() {
		$this->check_valid_page();
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
		$this->tabs[$this->caption]['id'] = count($this->tabs);
	}

	public function set_href($href) {
		$this->tabs[$this->caption]['href'] = $href;
	}

	public function end_tab() {
		$this->tabs[$this->caption]['body'] = ob_get_contents();
		ob_end_clean();		
		$this->tabs[$this->caption]['js'] = true;
	}

	private function check_valid_page()
	{
		if (is_numeric($this->page) && $this->page >= count($this->tabs)) {
			$this->page = count($this->tabs) - 1;
		}
	}

	/**
	 * @param $val
	 * @return string
	 */
	private function get_body($val)
	{
		$body = '';
		if (isset($val['func'])) {
			ob_start();
			if (!is_array($val['args'])) $val['args'] = array($val['args']);
			call_user_func_array($val['func'], $val['args']);
			$body .= ob_get_contents();
			ob_end_clean();
		} else {
			$body .= $val['body'];
		}
		return $body;
	}

}
?>
