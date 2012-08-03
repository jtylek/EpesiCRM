<?php
/**
 * Utils_Menu
 * Module for creating menus. Very easy.
 *
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Menu extends Module {
	//private static $menu_counter = 0;
	private $menu_id;
	private $menu_string;
	private $layout;

	public function construct( $arg = null) {
		if(!isset($arg)) {
			$arg = "vertical";
		}
		$this->menu_id = md5($this->get_path());//Utils_Menu::$menu_counter;
		$this->layout = $arg;
		$this->menu_string =
			'load_menu_'.$this->menu_id.' = function() {'.
			'	menubar_'.$this->menu_id.' = new CustomMenubar(\''.$this->menu_id.'\', "'.htmlspecialchars($arg).'");';
		//Utils_Menu::$menu_counter++;
		load_js("modules/Utils/Menu/js/menu.js");
	}

	/**
	 * Adds hyperlink to the menu.
	 *
	 * @param string displayed text
	 * @param string target address of the link
	 * @param string optional path to an icon
	 */
	public function add_link($title, $address='', $icon=null, $target=null) {
		$this->menu_string .= 'menubar_'.$this->menu_id.'.addLink("'.htmlspecialchars($title).'"';
		if(isset($address)) {
			$this->menu_string .= ', "'.addslashes($address).'"';
			if(isset($icon)) {
				$this->menu_string .= ', "'.addslashes($icon).'"';
			}
			if(isset($target)) {
				$this->menu_string .= ', "'.addslashes($target).'"';
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
	 * This method displays menu.
	 */
	public function body() {

		$this->help('Menu','index');

		$theme = $this->init_module('Base/Theme');
		$str = '<div id=menu_contener_'.$this->menu_id.'><img width="16" height="16" border="0" style="width: 16px; height: 16px; margin-top: 2px; margin-left: 2px; background-color: white; color: white; border: 0px;" src="modules/Utils/Menu/theme/loader.gif"></div>';
		$theme->assign('menu', $str);
		$theme->display();
		$this->menu_string .= 'writeOut(\''.$this->menu_id.'\');';
		$this->menu_string .= '}; ';
		$this->menu_string .= 'wait_while_null( "CustomMenubar", "load_menu_'.$this->menu_id.'(12)" );';
		$new_md5 = md5($this->menu_string);
		$old_md5 = & $this->get_module_variable('old');
		if($new_md5!=$old_md5) {
			eval_js($this->menu_string);
			$old_md5 = $new_md5;
		}
	}

	public function reloaded() {
		eval_js($this->menu_string);
	}
}

?>
