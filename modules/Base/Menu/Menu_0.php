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
 * @copyright Copyright &copy; 2008, Janusz Tylek
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

	/**
	 * Renders the menu tree as a static Bootstrap collapse ("accordion") nav.
	 *
	 * Utils_Menu's vertical mode is built for a floating menu: submenus are
	 * hover-triggered fly-outs that menu.js positions with absolute coordinates.
	 * In a scrolling sidebar that is unusable - and on touch there is no hover at
	 * all - so adminlte renders its own markup here instead. Nothing else uses
	 * this path, and Utils_Menu is untouched.
	 *
	 * Mirrors build_menu()'s handling of icons, __url__, __split__ and the
	 * unset() sequence that turns an entry into its own submenu contents, and
	 * emits the same helpID values so the Base_Help tutorials keep working.
	 *
	 * @param array $m menu tree
	 * @param string $prefix helpID prefix, matching build_menu()
	 * @param int $depth nesting level, 0 at the top
	 * @return string HTML
	 */
	private function build_menu_html(& $m, $prefix = '', $depth = 0) {
		// Deliberately NOT AdminLTE's sidebar-menu/nav-treeview classes: it hides
		// .nav-treeview unless the parent carries its own .menu-open class, which
		// would fight the Bootstrap collapse used here. Own class names keep that
		// stylesheet out of it entirely.
		$out = '<ul class="nav flex-column' . ($depth ? ' epesi-submenu' : ' epesi-menu') . '">';
		foreach ($m as $k => $arr) {
			if ($k == '__split__') {
				$out .= '<li class="nav-item"><hr class="menu-split"></li>';
				continue;
			}

			// Menu entries carry an arbitrary module-provided icon filename (or
			// none at all - falling back to the module's own icon-small.png),
			// not a name from a fixed enum. Base_AdminlteIcons is the single
			// shared map for this (also used by the ActionBar's quick-access
			// launcher/launchpad icons, so a module's icon reads the same in
			// both places); an unmatched entry falls back to a plain
			// folder/dot rather than Base_AdminlteIcons's own generic default,
			// since menu icons are mostly filler art to begin with.
			require_once('modules/Base/Theme/adminlte_icons.php');
			$icon_raw = $arr['__icon_small__'] ?? $arr['__icon__'] ?? null;
			$parent_module = $arr['parent_module'] ?? null;
			unset($arr['__icon_small__'], $arr['__icon__'], $arr['parent_module']);

			$is_sub = array_key_exists('__submenu__', $arr);
			$bi_icon = Base_AdminlteIcons::resolve($icon_raw, $parent_module, $is_sub ? 'bi-folder2' : 'bi-dot');

			$tip = '';
			if (array_key_exists('__description__', $arr)) {
				$tip = ' title="' . htmlspecialchars($arr['__description__'], ENT_QUOTES) . '"';
				unset($arr['__description__']);
			}

			$target = '';
			$url = null;
			if (array_key_exists('__url__', $arr)) {
				$url = $arr['__url__'];
				unset($arr['__url__']);
				if (array_key_exists('__target__', $arr)) {
					$target = $arr['__target__'];
					unset($arr['__target__']);
				} else {
					$target = '_blank';
				}
			}

			$label = htmlspecialchars(_V($k)); // ****** Menu - translate labels
			$help_id = $prefix . $k;
			$img = '<i class="bi ' . htmlspecialchars($bi_icon, ENT_QUOTES) . ' nav-icon"></i>';

			if ($is_sub) {
				unset($arr['__submenu__']);
				// ids must be unique and valid, and menu labels are arbitrary text
				$id = 'epesi_menu_' . md5($help_id);
				$out .= '<li class="nav-item">'
					. '<a href="#" class="nav-link menu-parent collapsed" data-bs-toggle="collapse"'
					. ' data-bs-target="#' . $id . '" aria-expanded="false" aria-controls="' . $id . '"'
					. ' helpID="' . htmlspecialchars($help_id, ENT_QUOTES) . '"' . $tip . '>'
					. $img . '<span class="nav-label">' . $label . '</span>'
					. '<i class="bi bi-chevron-right nav-arrow"></i></a>'
					. '<div class="collapse" id="' . $id . '">'
					. $this->build_menu_html($arr, $prefix . $k . '_', $depth + 1)
					. '</div></li>';
			} else {
				$href = $url !== null
					? $url
					: 'javascript:' . Base_MenuCommon::create_href_js($this, $arr);
				$out .= '<li class="nav-item"><a class="nav-link" href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
					. ($target ? ' target="' . htmlspecialchars($target, ENT_QUOTES) . '"' : '')
					. ' helpID="' . htmlspecialchars($help_id, ENT_QUOTES) . '"' . $tip . '>'
					. $img . '<span class="nav-label">' . $label . '</span></a></li>';
			}
		}
		return $out . '</ul>';
	}

	private function build_menu(& $menu, & $m, $prefix='') {
		foreach($m as $k=>$arr) {
			if($k=='__split__')
				$menu->add_split();
			else {
				$icon = null;
				if(array_key_exists('__icon_small__',$arr)) {
					if (is_readable($arr['__icon_small__']))
						$icon = $arr['__icon_small__'];
					else
						$icon = Base_ThemeCommon::get_template_file($arr['parent_module'], $arr['__icon_small__']);
					unset($arr['__icon_small__']);
					unset($arr['__icon__']);
				} else if(array_key_exists('__icon__',$arr)) {
					if (is_readable($arr['__icon__']))
						$icon = $arr['__icon__'];
					else
						$icon = Base_ThemeCommon::get_template_file($arr['parent_module'], $arr['__icon__']);
					unset($arr['__icon__']);
				} else {
					if(isset($arr['parent_module']) && is_string($arr['parent_module']))
						$icon = Base_ThemeCommon::get_template_file($arr['parent_module'], 'icon-small.png');
				}
				if (!$icon) {
					if(array_key_exists('__submenu__', $arr))
						$icon = Base_ThemeCommon::get_template_file('Base_Menu', 'folder.png');
					else
						$icon = Base_ThemeCommon::get_template_file('Base_Menu', 'element.png');
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

				$label = _V($k); // ****** Menu - translate labels
				if(array_key_exists('__submenu__', $arr)) {
					unset($arr['__submenu__']);
					$menu->begin_submenu($label,$icon,$prefix.$k);
					$this->build_menu($menu, $arr, $prefix.$k.'_');
					$menu->end_submenu();
				} else {
					if($url)
						$menu->add_link($label, $url,$icon, $target, $prefix.$k);
					else {
						$menu->add_link($label, 'javascript:'.Base_MenuCommon::create_href_js($this,$arr) ,$icon, '', $prefix.$k);
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
		$aw = self::$tmp_menu[$a]['__weight__'] ?? 0;
		$bw = self::$tmp_menu[$b]['__weight__'] ?? 0;
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

	public function body() {
		// preparing modules menu and tools menu
		$modules_menu = array();
		$menus = Base_MenuCommon::get_menus();
		foreach($menus as $name=>$module_menu) {
				Base_MenuCommon::add_default_menu($module_menu, $name);
				self::add_menu($modules_menu,$module_menu);
		}
		if (!empty($modules_menu)) $modules_menu['__submenu__'] = 1;

		self::sort_menus($modules_menu);

		// Home menu
		$home_menu = array();
		$home_menu['Menu'] = $modules_menu;

		// putting all menus into menu array
		$menu = $home_menu;

		$theme = $this->init_module(Base_Theme::module_name());

		if (Base_ThemeCommon::is_adminlte_family()) {
			// The whole tree normally hangs off a single "Menu" root, which suits a
			// top-bar dropdown but collapses an entire sidebar into one row - so the
			// sidebar is built from $modules_menu directly, putting each top-level
			// group on screen. Rendered server-side as a Bootstrap accordion rather
			// than through Utils/Menu, whose submenus are hover fly-outs (see
			// build_menu_html()).
			$sidebar = $modules_menu;
			unset($sidebar['__submenu__']);
			$theme->assign('menu', $this->build_menu_html($sidebar, 'Menu_'));
		} else {
			$menu_mod = $this->init_module("Utils/Menu", "horizontal");
			$this->build_menu($menu_mod,$menu);
			$menu_mod->set_inline_display();
			$theme->assign('menu', $this->get_html_of_module($menu_mod));
		}

		$theme->display();

	}
}
?>
