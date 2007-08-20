<?php
/** 
 * Utils_Menu
 * Module for creating menus. Very easy.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com> 
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9 
 * @licence SPL
 * @package epesi-utils 
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Menu extends Module {
	private static $menu_counter = 0;
	private $menu_id;
	private $menu_string;
	private $layout;
	
	public function construct( $arg = null) {
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
	
	/**
	 * Adds hyperlink to the menu.
	 * 
	 * @param string displayed text
	 * @param string target address of the link
	 * @param string optional path to an icon
	 */
	public function add_link($title, $address='', $icon=null) {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.addLink("'.htmlspecialchars($title).'"';
		if(isset($address)) {
			$this->menu_string .= ', "'.addslashes($address).'"';
			if(isset($icon)) {
				$this->menu_string .= ', "'.addslashes($icon).'"';
			}
		}
		$this->menu_string .= ');';
	}	
	
	/**
	 * Adds a splitting line to the menu. Useful when you want to 
	 * divide menu into sctions without using submenus.
	 */
	public function add_split() {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.addSplit();';
	}
	
	/**
	 * Begins submenu. Everything placed between 'begin_submenu' and 'end_submenu' 
	 * is conthent of that submenu.
	 * 
	 * @param string name of the submenu
	 * @param string optional path to an icon
	 */
	public function begin_submenu($title, $icon=null) {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.beginSubmenu("'.htmlspecialchars($title).'"';
		if(isset($icon)) {
			$this->menu_string .= ', "'.addslashes($icon).'"';
		}
		$this->menu_string .= ');';
	}
	/**
	 * Ends submenu.
	 */
	public function end_submenu() {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.endSubmenu();';
	}
	
	/**
	 * This method returns HTML code of generated menu.
	 */
	public function toHtml() {
		$this->menu_string .= 'writeOut('.$this->menu_id.');';
		$this->menu_string .= '}; '; 
				
		$this->menu_string .= 'wait_while_null( "CustomMenubar", "load_menu_'.$this->menu_id.'(12)" );';
		eval_js($this->menu_string);
		
		//$theme = & $this->init_module('Base/Theme');
		//$str = '<div id=menu_contener_'.$this->menu_id.'><img style="background: white; color: white; border: 1px solid black" src="modules/Utils/Menu/theme/loader.gif"></div>';
		Base_ThemeCommon::load_css('Utils/Menu');
		//load_css('data/');
		return '<div id=menu_contener_'.$this->menu_id.'><img src="modules/Utils_Menu/theme/loader.gif"></div>';
		//return $theme->toHTML();
	}
	
	/**
	 * This method displays menu.
	 */
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