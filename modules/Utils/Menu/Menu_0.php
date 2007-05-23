<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> 
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9 
 * @package tcms-utils 
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Menu extends Module {
	private static $menu_counter = 0;
	private $menu_id;
	private $menu_string;
	private $layout;
	
	public function construct( $arg ) {
		if(!isset($arg)) {
			$arg = "vertical";
		}
		$this->menu_id = Utils_Menu::$menu_counter;
		$this->layout = $arg;
		$this->menu_string = 
			'load_menu_'.$this->menu_id.' = function() {'.
			'	menubar_'.$this->menu_id.' = new CustomMenubar('.$this->menu_id.', "'.htmlspecialchars($arg).'");'
		;
		Utils_Menu::$menu_counter++;
		load_js("modules/Utils/Menu/js/menu.js");
	}
	
	public function add_link($title, $address, $icon) {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.addLink("'.htmlspecialchars($title).'"';
		if(isset($address)) {
			$this->menu_string .= ', "'.addslashes($address).'"';
			if(isset($icon)) {
				$this->menu_string .= ', "'.addslashes($icon).'"';
			}
		}
		$this->menu_string .= ');';
	}
	public function add_split($title) {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.addSplit();';
	}
	public function begin_submenu($title, $icon) {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.beginSubmenu("'.htmlspecialchars($title).'"';
		if(isset($icon)) {
			$this->menu_string .= ', "'.addslashes($icon).'"';
		}
		$this->menu_string .= ');';
	}
	public function end_submenu() {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.endSubmenu();';
	}
	
	public function toHtml() {
		$this->menu_string .= 'writeOut('.$this->menu_id.');';
		$this->menu_string .= '}; '; 
				
		$this->menu_string .= 'wait_while_null( "CustomMenubar", "load_menu_'.$this->menu_id.'(12)" );';
		eval_js($this->menu_string);
		
		//$theme = & $this->init_module('Base/Theme');
		//$str = '<div id=menu_contener_'.$this->menu_id.'><img style="background: white; color: white; border: 1px solid black" src="modules/Utils/Menu/theme/loader.gif"></div>';
		Base_ThemeCommon::load_css('Utils/Menu');
		//load_css('data/');
		return '<div id=menu_contener_'.$this->menu_id.'><img src="modules/Utils/Menu/theme/loader.gif"></div>';
		//return $theme->toHTML();
	}
	
	public function body() {
		$theme = & $this->init_module('Base/Theme');
		$str = '<div id=menu_contener_'.$this->menu_id.'><img style="background: white; color: white; border: 1px solid black" src="modules/Utils/Menu/theme/loader.gif"></div>';
		$theme->assign('menu', $str);
		$theme->display();
		$this->menu_string .= 'writeOut('.$this->menu_id.');';
		$this->menu_string .= '}; '; 
				
		$this->menu_string .= 'wait_while_null( "CustomMenubar", "load_menu_'.$this->menu_id.'(12)" );';
		eval_js($this->menu_string);
	}
}