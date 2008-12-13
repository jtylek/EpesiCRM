<?php
/**
 * Menu class.
 *
 * Provides layout to Menu module.
 *
 *
 * ** Creating menu **
 *
 * A module will use Menu module functionality if it defines at least one of three methods:
 * - menu() - Menu in 'Modules' section, each option will automatically link to the module body
 * - Each value is a menu option
 * - Options labels are created based on array keys
 * - value is an array that defines variables: $key=>$value
 * - Alternatively you can place __submenu__ under an option. In this case option will hold an array constructed as described above with additional value '__submenu__'=>1.
 * Other special array keys:
 * - __split__ - line to split menu entries
 * - __icon__ - url to icon
 * - __description__ - description
 * - __url__ - open url instead of automatic generated epesi link... probably usable only with external sites.
 * - __target__ - for example you can pass '_blank' to open link in new window... usable only with __url__
 * - __module__ - module to pack as main module
 * - __function__ - function to call
 * - __function_arguments__ - string argument passed to function
 * - __constructor_arguments__ - string argument passed to function
 * - __weight__ - integer that specifies weight of menu entry
 * Example:
 *  return array(	'Label 1'=>array('variable1'=>'value2'),
 *  				'Label 2'=>array('variable1'=>'value2'));
 * You should limit number of labels on the top level to minimum (preferably one). If you need more options, place them in __submenu__:
 *  return array('My module menu'=>array(	'Label 1'=>array('variable1'=>'value2'),
 * 											'__split__'=>1,
 *  										'Label 2'=>array('variable2'=>'value3'),
 *  										'Label 3'=>array('variable3'=>'value4'),
 *  										'__submenu__'=>1));
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage menu
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu extends Module {
	private static $menu;
	private $menu_name;
	private static $menu_module = array();
	private static $tmp_menu;
	private $duplicate = false;

	private function build_menu(& $menu, & $m) {
		foreach($m as $k=>$arr) {
			if($k=='__split__')
				$menu->add_split();
			else {
				if(array_key_exists('__submenu__', $arr))
					$icon = Base_ThemeCommon::get_template_file('Base_Menu', 'folder.png');
				else
					$icon = Base_ThemeCommon::get_template_file('Base_Menu', 'element.png');
				if(array_key_exists('__icon_small__',$arr)) {
					try {
						$icon = Base_ThemeCommon::get_template_file($arr['parent_module'], $arr['__icon_small__']);
					} catch(Exception $e) {
					}
					unset($arr['__icon_small__']);
					unset($arr['__icon__']);
				} else if(array_key_exists('__icon__',$arr)) {
					try {
						$icon = Base_ThemeCommon::get_template_file($arr['parent_module'], $arr['__icon__']);
					} catch(Exception $e) {
					}
					unset($arr['__icon__']);
				} else {
					try {
						if(isset($arr['parent_module']) && is_string($arr['parent_module']))
							$icon = Base_ThemeCommon::get_template_file($arr['parent_module'], 'icon-small.png');
					} catch(Exception $e) {
					}
				}
				unset($arr['parent_module']);

				if(array_key_exists('__description__',$arr)) {
					$description = "'".$arr['__description__']."'";
					unset($arr['__description__']);
				} else
					$description = 'null';

				if(array_key_exists('__url__',$arr)) {
					$url = $arr['__url__'];
					unset($arr['__url__']);
					if(array_key_exists('__target__',$arr)) {
						$target = $arr['__target__'];
						unset($arr['__target__']);
					} else
						$target = '_blank';
				} else
					$url = null;

				if(array_key_exists('__submenu__', $arr)) {
					unset($arr['__submenu__']);
					$menu->begin_submenu(Base_LangCommon::ts('Base_Menu',$k),$icon);
					$this->build_menu($menu, $arr);
					$menu->end_submenu();
				} else {
					if($url)
						$menu->add_link(Base_LangCommon::ts('Base_Menu',$k), $url,$icon);
					else {
						$menu->add_link(Base_LangCommon::ts('Base_Menu',$k), 'javascript:'.Base_MenuCommon::create_href_js($this,$arr) ,$icon);
					}
				}
			}
		}
	}

	private static function add_menu(& $menu,$addon){
		if(!is_array($addon)) return;
		foreach($addon as $k=>$v){
			if (!array_key_exists($k,$menu)){
				$menu[$k] = $v;
			} else {
				if (is_array($menu[$k]) && array_key_exists('__submenu__',$menu[$k])) {
					self::add_menu($menu[$k],$v);
//					ksort($menu[$k]);
				} elseif(is_array($v)) {
					$c = Base_LangCommon::ts('Base/Menu','submenu');
					if(is_array($menu[$k]) && array_key_exists('__submenu__',$menu[$k]))
						$menu[$k][str_replace('_',': ',$v['box_main_module'])] = $v;
					elseif(is_array($v) && array_key_exists('__submenu__',$v)) {
						$old = $menu[$k];
						$menu[$k] = $v;
						$menu[$k][str_replace('_',': ',$old['box_main_module'])] = $old;
					} else
						$menu[$k] = array(
							str_replace('_',': ',$menu[$k]['box_main_module']) =>$menu[$k],
							'__submenu__'=>1,
							str_replace('_',': ',$v['box_main_module'])=>$v);
				}
			}
		}
	}

	public static function sort_menus_cmp($a, $b) {
		$aw = isset(self::$tmp_menu[$a]['__weight__']) ? self::$tmp_menu[$a]['__weight__']:0;
		$bw = isset(self::$tmp_menu[$b]['__weight__']) ? self::$tmp_menu[$b]['__weight__']:0;
		if(!isset($aw) || !is_numeric($aw)) $aw=0;
		if(!isset($bw) || !is_numeric($bw)) $bw=0;
		if($aw==$bw)
			return strcasecmp($a, $b);
//		trigger_error('='.$aw."=".print_r($bw,true).'=',E_USER_ERROR);
		return $aw-$bw;
	}

	private static function sort_menus(& $menu) {
		self::$tmp_menu = $menu;
		uksort($menu, array("Base_Menu","sort_menus_cmp"));
		foreach($menu as &$m) {
			if(is_array($m) && array_key_exists('__submenu__',$m))
				self::sort_menus($m);
			else
				unset($m['__weight__']);
		}
		unset($menu['__weight__']);
	}

	public function body() {
		// preparing modules menu and tools menu
		$modules_menu = array();
		$menus = Base_MenuCommon::get_menus();
		foreach($menus as $name=>$module_menu) {
				Base_MenuCommon::add_default_menu($module_menu, $name);
				self::add_menu($modules_menu,$module_menu);
		}
		if (!empty($modules_menu)) $modules_menu['__submenu__'] = 1;

		// preparing quick access menu
		if (array_key_exists('Base_Menu_QuickAccess',ModuleManager::$modules)){
			$qaccess_menu = Base_Menu_QuickAccessCommon::quick_access_menu();
			if(is_array($qaccess_menu)) {
				Base_MenuCommon::add_default_menu($qaccess_menu, 'Base_Menu_QuickAccess');
			} else $qaccess_menu = array();
		} else $qaccess_menu = array();

		// preparing quick menu
		$current_module_menu = array();
                $qm = Base_MenuCommon::get_quick_menu();
                foreach($qm as $name=>$action) {
                    $r = explode('/',trim($name,'/'));
                    $curr = & $current_module_menu;
                    for($i=0; $i<count($r)-1; $i++) {
                        if(!isset($curr[$r[$i]]))
                            $curr[$r[$i]] = array('__submenu__'=>1);
                        $curr = & $curr[$r[$i]];
                    }
                    $curr[$r[count($r)-1]] = array('__url__'=>'javascript:('.$action.')');
                }

		self::sort_menus($modules_menu);

		// Home menu
		$home_menu = array();
		$home_menu[$this->ht('Menu')] = $modules_menu;

		// putting all menus into menu array
		$menu = array();
		$menu = array_merge($menu,$home_menu);
		$menu = array_merge($menu,$qaccess_menu);
		$menu = array_merge($menu,$current_module_menu);

		// preparing menu string
		$menu_mod = & $this->init_module("Utils/Menu", "horizontal");
		$this->build_menu($menu_mod,$menu);
//		self::$menu_module = $this->get_path();

//		$this->menu_name = $this->get_name().$this->get_instance_id().'menu';

		$theme = & $this->init_module('Base/Theme');

		$menu_mod->set_inline_display();
		$theme->assign('menu', $this->get_html_of_module($menu_mod));

		$theme->display();

	}
}
?>
