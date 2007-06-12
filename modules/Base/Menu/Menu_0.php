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
 * - menu_tool() - Menu in 'Tools' section, each option will automatically link to the module body
 * - quick_menu() - Separate menu that will be displayed only if the module is active (is placed in Container_0 in Box module)
 * quick_menu variables are accessible via get_unique_href_variable function. 
 * Menu content is passed with an array. The array should be created as follows:
 * - Each value is a menu option
 * - Options labels are created based on array keys
 * - value is an array that defines variables: $key=>$value
 * - Alternatively you can place __submenu__ under an option. In this case option will hold an array constructed as described above with additional value '__submenu__'=>1.
 * Other special array keys:
 * - __split__ - line to split menu entries
 * - __icon__ - url to icon
 * - __description__ - description
 * - __url__ - open url instead of automatic generated tcms link... probably usable only with external sites.
 * - __target__ - for example you can pass '_blank' to open link in new window... usable only with __url__
 * - __module__ - module to pack as main module
 * - __function__ - function to call
 * - __function_arguments__ - string argument passed to function
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
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Provides layout to Menu module.
 * @package tcms-base-extra
 * @subpackage menu
 */
class Base_Menu extends Module {
	private static $menu;
	private $menu_name;
	private static $menu_module = array();
	private static $tmp_menu;
	private $duplicate = false;
	
	public function construct() {
		$this->fast_process();
	}
	
	
	public static function add_default_menu(& $m, $name) {
		foreach($m as $k=>$arr) {
			if(array_key_exists('__submenu__', $arr)) 
				self::add_default_menu($m[$k], $name);
			elseif(is_array($arr)) {
				if(array_key_exists('__module__',$arr)) {
					$action = array('box_main_module'=>$arr['__module__'],'menu_click'=>1);
					unset($arr['__module__']);
				} else
					$action = array('box_main_module'=>$name,'menu_click'=>1);
				if(array_key_exists('__function__',$arr)) {
					$action['box_main_function']=$arr['__function__'];
					unset($arr['__function__']);
				}
				if(array_key_exists('__function_arguments__',$arr)) {
					$action['box_main_arguments']=$arr['__function_arguments__'];
					unset($arr['__function_arguments__']);
				}
				$m[$k] = array_merge($action,$arr);
			} elseif($k!='__icon__' && $k!='__description__' && $k!='__url__' && $k!='__target__' && $k!='__weight__' && $k!='__function__' && $k!='__function_arguments__')
				$m[$k] = array();
		}
	} 
	
	private static function build_menu(& $menu, & $m) {
		foreach($m as $k=>$arr) {
			if($k=='__split__')
				$menu->add_split();
			else {
				if(array_key_exists('__icon__',$arr)) {
					$icon = "'".$arr['__icon__']."'";
					unset($arr['__icon__']);
				} else
					$icon = 'null';
	
				if(array_key_exists('__description__',$arr)) {
					$description = "'".$arr['__description__']."'";
					unset($arr['__description__']);
				} else
					$description = 'null';

				if(array_key_exists('__url__',$arr)) {
					$url = "'".$arr['__url__']."'";
					unset($arr['__url__']);
					if(array_key_exists('__target__',$arr)) {
						$target = "'".$arr['__target__']."'";
						unset($arr['__target__']);
					} else
						$target = 'null';
				} else
					$url = null;
					
				if(is_array($arr) && array_key_exists('__submenu__', $arr)) {
					unset($arr['__submenu__']);
					$menu->begin_submenu(Base_LangCommon::ts('Base_Menu',$k));
					self::build_menu($menu, $arr);
					$menu->end_submenu();
//					$ret[] = "[$icon,'".Base_LangCommon::ts('Base_Menu',$k)."', null, null, $description, ".self::build_menu($arr)."]";
				} else
					if($url)
//						$ret[] = "[$icon,'".Base_LangCommon::ts('Base_Menu',$k)."', $url, $target, $description]";
						$menu->add_link(Base_LangCommon::ts('Base_Menu',$k), $url);
					else
						$menu->add_link(Base_LangCommon::ts('Base_Menu',$k), 'javascript:'.Module::create_href_js($arr) );
//						$ret[] = "[$icon,'".Base_LangCommon::ts('Base_Menu',$k)."', 'javascript:".addslashes(Module::create_href_js($arr))."', null, $description]";
			}
		}
	}
	
	private static function add_menu(& $menu,$addon){
//		print_r($addon);
		foreach($addon as $k=>$v){
//			print($k.'<br>');
			if (!array_key_exists($k,$menu)){
				$menu[$k] = $v;
			} else {
				if (array_key_exists('__submenu__',$menu[$k])) {
					self::add_menu($menu[$k],$v);
//					ksort($menu[$k]);
				} else {
					$menu[$k] = array(
						str_replace('_',': ',$menu[$k]['box_main_module']) =>$menu[$k], 
						'__submenu__'=>array(),
						str_replace('_',': ',$v['box_main_module'])=>$v);
				}
			}
		}
	}
	
	public static function sort_menus_cmp($a, $b) {
		$aw = self::$tmp_menu[$a]['__weight__'];
		$bw = self::$tmp_menu[$b]['__weight__'];
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
			if(array_key_exists('__submenu__',$m))
				self::sort_menus($m);
			else
				unset($m['__weight__']);
		}
		unset($menu['__weight__']);
	}
	
	public function body($arg) {
		global $base;
		$lang = $this->pack_module('Base/Lang');
		
		// preparing modules menu and tools menu
		$modules_menu = array();
		$tools_menu = array();
		foreach($base->modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'menu')) {
				$module_menu = call_user_func(array($obj['name'].'Common','menu'));
				if(!is_array($module_menu)) continue;
				self::add_default_menu($module_menu, $name);
				self::add_menu($modules_menu,$module_menu);
			}
			if(method_exists($obj['name'].'Common', 'tool_menu')) {
				$module_menu = call_user_func(array($obj['name'].'Common','tool_menu'));
				if(!is_array($module_menu)) continue;
				self::add_default_menu($module_menu, $name);
				self::add_menu($tools_menu,$module_menu);
			}
		}
		if (!empty($modules_menu)) $modules_menu['__submenu__'] = 1;
		if (!empty($tools_menu)) $tools_menu['__submenu__'] = 1;
		
		// preparing admin menu
		if (array_key_exists('Base_Admin',$base->modules)){
			$admin_menu = call_user_func(array('Base_Admin','admin_menu'));
			if(is_array($admin_menu)) {
				self::add_default_menu($admin_menu, 'Base_Admin');
			} else $admin_menu = array();
		} else $admin_menu = array();
		
		// preparing quick access menu
		if (array_key_exists('Base_Menu_QuickAccess',$base->modules)){
			$qaccess_menu = call_user_func(array('Base_Menu_QuickAccess','quick_access_menu'));
			if(is_array($qaccess_menu)) {
				self::add_default_menu($qaccess_menu, 'Base_Menu_QuickAccess');
			} else $qaccess_menu = array();
		} else $qaccess_menu = array();
		
		// preparing quick menu
		$current_module_menu = array();
		$box_module = ModuleManager::get_instance('/Base_Box|0');
		if($box_module)
			$active_module = $box_module->get_main_module();
		if($active_module) {
			$first_child = true;				
			$current_module_menu = call_user_func(array($active_module->get_type().'Common','quick_menu'));
			if(is_array($current_module_menu)) {
				self::add_unique_keys($current_module_menu,$active_module);
			} else {
				$current_module_menu = array();
				$first_child = false;
			}
		
			// preparing children quick menu
			$current_module_children_menu = array();
			$children = $active_module->get_children();
			foreach($children as $k=>$mod)
					if(method_exists($mod->get_type().'Common', 'quick_menu')) {
							$module_menu = call_user_func(array($mod->get_type().'Common','quick_menu'));
							if(!is_array($module_menu)) continue;
							self::add_unique_keys($module_menu,$mod);
							if (empty($current_module_menu)) {
								$current_module_menu = array($lang->ht('Main')=>array());
							} else {
								reset($current_module_menu);
								if ($first_child) $current_module_menu[key($current_module_menu)] = array_merge($current_module_menu[key($current_module_menu)],array('<hr>'=>1,'__submenu__'=>1));
								$first_child = false; 
							}
							$current_module_children_menu = array_merge($current_module_children_menu,$module_menu);
							$current_module_children_menu['__submenu__'] = 1;
					}
			// filling quick menu with children menu
			reset($current_module_menu);
			if (!empty($current_module_menu)) $current_module_menu[key($current_module_menu)] = array_merge($current_module_menu[key($current_module_menu)],$current_module_children_menu);
		}
		
		//print_r($modules_menu);
		self::sort_menus($modules_menu);
		self::sort_menus($tools_menu);
		// sorting menus
//		ksort($modules_menu);
//		ksort($tools_menu);

		// Home menu
		$home_menu = array();
		$admin_menu['__submenu__'] = 1;
		$tools_menu = array($lang->ht('Tools')=>array_merge($tools_menu,array('__submenu__' => 1)));
//		$home_menu[$lang->t('Home',true)] = array_merge($admin_menu,$tools_menu,array('__split__'=>1),$modules_menu);
		$home_menu[$lang->ht('Home')] = array_merge($modules_menu,array('__split__'=>1),$admin_menu,$tools_menu);

		// putting all menus into menu array
		$menu = array();
//		if (!empty($modules_menu)) $menu[$lang->t('Modules',true)] = $modules_menu;
		$menu = array_merge($menu,$current_module_menu);
		$menu = array_merge($menu,$qaccess_menu);
//		if (!empty($tools_menu)) $menu[$lang->t('Tools',true)] = $tools_menu;
		$menu = array_merge($menu,$home_menu);

		// preparing menu string
		$menu_mod = $this->init_module("Utils/Menu", "horizontal");
		self::build_menu($menu_mod,$menu);
//		self::$menu_module = $this->get_path();		
				
//		$this->menu_name = $this->get_name().$this->get_instance_id().'menu';
		
		$theme = & $this->init_module('Base/Theme');
		
//		$theme->assign('menu', '<div id="'.$this->menu_name.'">');
		$theme->assign('menu', $menu_mod->toHtml());

/*		$mmd5 = md5(self::$menu);
		$smd5 = $this->get_module_variable('md5');
		if(!isset($smd5) || $smd5!=$mmd5) {
			if(!isset($smd5)) 
				eval_js("load_js('modules/Base/Menu/menulib.php')");
			eval_js("wait_while_null('main_menu','main_menu = [".addslashes(self::$menu)."]')");
			$this->set_module_variable('md5',$mmd5);
			$this->set_reload(true);
		} 
		
*/
		$theme->display();
		
	}

	public function reloaded() {
//		    eval_js("wait_while_null('print_menu','print_menu(\'".$this->menu_name."\')')");
	}
	
	public static function add_unique_keys(& $arr, & $module){
		foreach ($arr as $k=>& $v){
			if (array_key_exists('__submenu__',$v)){
				self::add_unique_keys($v, $module);				
			} else {
				$new = array();
				foreach ($v as $a => $b){
					$new[$module->create_unique_key($a)] = $b;
				}
				$v = $new;
			}
		}
	}
}
?>
