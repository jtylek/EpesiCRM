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
 *  return array(	_M('Label 1')=>array('variable1'=>'value2'),
 *  				_M('Label 2')=>array('variable1'=>'value2'));
 * You should limit number of labels on the top level to minimum (preferably one). If you need more options, place them in __submenu__:
 *  return array(_M('My module menu')=>array(	_M('Label 1')=>array('variable1'=>'value2'),
 * 											'__split__'=>1,
 *  										_M('Label 2')=>array('variable2'=>'value3'),
 *  										_M('Label 3')=>array('variable3'=>'value4'),
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

	private static function add_menu(& $menu,$addon){
		if(!is_array($addon)) return;
		foreach($addon as $k=>$v){
			if (!array_key_exists($k,$menu)){
				$menu[$k] = $v;
			} else {
				if (is_array($menu[$k]) && array_key_exists('__submenu__',$menu[$k])) {
					self::add_menu($menu[$k],$v);
				} elseif(is_array($v)) {
					$c = __('submenu');
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
		return $aw-$bw;
	}

    private static function sort_menus(& $menu) {
        self::$tmp_menu = $menu;
        uksort($menu, array("Base_Menu","sort_menus_cmp"));
        foreach($menu as &$m) {
            if(is_array($m) && array_key_exists('__submenu__',$m))
                self::sort_menus($m);
			elseif(is_array($m))
                unset($m['__weight__']);
        }
        unset($menu['__weight__']);
    }

	public function body()
	{
        // preparing modules menu and tools menu
        $modules_menu = array();
        $menus = Base_MenuCommon::get_menus();
        foreach ($menus as $name => $module_menu) {
            Base_MenuCommon::add_default_menu($module_menu, $name);
            self::add_menu($modules_menu, $module_menu);
        }
        if (!empty($modules_menu)) $modules_menu['__submenu__'] = 1;

        self::sort_menus($modules_menu);

        Base_MenuCommon::generate_urls($this, $modules_menu);

		load_js($this->get_module_dir().'/libs/jquery.smartmenus.min.js');
		load_js($this->get_module_dir().'/libs/jquery.smartmenus.bootstrap.min.js');
		load_css($this->get_module_dir().'/libs/jquery.smartmenus.bootstrap.css');

		return $this->twig_display('default.twig', array(
			'menu' => Base_MenuCommon::build_menu($modules_menu),
			'name' => __('Menu')
		));
	}
}
?>
