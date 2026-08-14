<?php
/**
 * QuickAccess class.
 *
 * This class provides functionality for QuickAccess class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage menu-quickaccess
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-star'; }

	private static $options = null;

	public static function user_settings() {
		self::get_options();
		$ret_opts = array();
		$grandfathered = self::get_grandfathered_items();
		foreach(self::$options as $opt) {
			unset($opt['link']);
			$name = $opt['name'];
			unset($opt['name']);
			// Only items that existed as of the last freeze (see
			// freeze_current_items_as_grandfathered()) default to visible -
			// anything installed afterward (e.g. a Premium module added later
			// via EpesiStore) starts hidden instead, so installing a new
			// module no longer clutters every user's Quick Access bar/
			// Launchpad the way Premium_Warehouse just did. Per-user rows in
			// base_user_settings still override this either way, same as
			// before.
			$default = in_array($name, $grandfathered) ? 1 : 0;
			$opt = array_merge($opt,array(
						'type'=>'bool',
						'reload'=>true,
						'default'=>$default
						));
			// Default on for both columns, except the Dashboard module's own
			// entry: showing a "Dashboard" widget on the Dashboard itself is
			// circular/pointless, so only its Launchpad default stays on.
			$is_dashboard = ($opt['module']==Base_Dashboard::module_name());
			$dashboard_default = $is_dashboard?0:$default;
			$dashboard_elem = array_merge($opt,array(
						'values'=>__('Dashboard'),
						'default'=>$dashboard_default,
						'name'=>$name.'_d'));
			$launchpad_elem = array_merge($opt,array(
						'values'=>__('Launchpad'),
						'name'=>$name.'_l'));
			if ($is_dashboard) {
				// Locked, not just defaulted, per request: a "Dashboard"
				// quick-access icon inline on the Dashboard itself is
				// pointless (see above), and Dashboard's own Launchpad entry
				// is the one guaranteed way back to it now that the
				// ActionBar's own per-item pin toggle for it is gone
				// (Base_Box/theme_adminltedark/default.tpl's permanent
				// navbar icon replaced it) - so unlike every other row,
				// neither checkbox here should be user-changeable at all.
				// 'disabled' on the QuickForm element (Libs_QuickForm's
				// get_element_by_array() merges this into the checkbox's own
				// HTML attributes) only stops it being changed FROM HERE ON;
				// the actual behaviour is enforced unconditionally in
				// ActionBar_0.php::body() regardless of whatever's already
				// stored for a user from before this lock existed, so a
				// disabled-but-stale-looking checkbox here (for someone who
				// toggled this row before today) doesn't leave the real
				// behaviour unlocked.
				$dashboard_elem['param'] = array('disabled'=>'disabled');
				$launchpad_elem['param'] = array('disabled'=>'disabled');
				$launchpad_elem['default'] = 1;
			}
			// Both elems share the group's own 'label' (the module item's name,
			// used as the row header) - each elem's own 'values' is its column
			// caption, shown inline by non-adminlte themes and used to build the
			// "Dashboard"/"Launchpad" column headers in the adminlte theme (see
			// Base_User_Settings::body()).
			$ret_opts[] = array('type'=>'group', 'label'=>$opt['label'], 'elems'=>array($dashboard_elem, $launchpad_elem));
		}
		//trigger_error(print_r($ret_opts,true));
		if (Acl::is_user()) return array(__('Quick Access')=>$ret_opts);
		return array();
	}

	// The set of item names (get_options()'s own 'name', an md5 of the menu
	// path - the same key used for the _d/_l setting rows) that still
	// default to visible. Empty/never-frozen means every item defaults to
	// hidden, so an install that never freezes still fails safe rather than
	// silently reverting to "everything on".
	public static function get_grandfathered_items() {
		$items = Variable::get('quickaccess_grandfathered_items', false);
		return is_array($items) ? $items : array();
	}

	// Snapshots every menu item that exists right now as still defaulting to
	// visible - called once at the end of a fresh install's module-install
	// loop (FirstRun_0.php::done()) and once via the 20260814 patch for
	// installs upgrading into this behavior. Deliberately never called again
	// after that: a module installed later is exactly what's meant to start
	// hidden, so nothing should keep growing this set back to "everything".
	public static function freeze_current_items_as_grandfathered() {
		self::$options = null; // force a recompute against the current menu, not a request-cached one
		$names = array_map(fn($opt) => $opt['name'], self::get_options());
		Variable::set('quickaccess_grandfathered_items', $names);
	}

	public static function get_options() {
		static $user;
		if (isset(self::$options) && $user==Acl::get_user()) return self::$options;
		$user = Acl::get_user();
		self::$options = array();
		$modules_menu = array();

		$menus = Base_MenuCommon::get_menus();
		//ksort($menus);
		foreach($menus as $name=>$ret) {
			if ($name=='Base_Admin') continue;
			if ($name==Base_Menu_QuickAccessCommon::module_name()) continue;
			Base_MenuCommon::add_default_menu($ret, $name);
			$modules_menu = array_merge($modules_menu,self::check_for_links('',$ret,$name));
		}
		usort($modules_menu,fn($a, $b) => strcmp($a['label'],$b['label']));
		self::$options = & $modules_menu;
		return self::$options;
	}

	private static function check_for_links($prefix,$array,$mod,$prefixt=''){
		$result = array();
		foreach($array as $k=>$v){
			if (str_starts_with($k, '__')) continue;
			$c_pre = $prefixt._V($k); // ****** Menu options label
			if (is_array($v) && array_key_exists('__submenu__',$v)) $result = array_merge($result,self::check_for_links($prefix.$k.': ',$v,$mod,$c_pre.': '));
			elseif(is_array($v)) {
				$result[] = array('name'=>md5($prefix.$k)
							,'link'=>$v
							,'label'=>$c_pre
							,'module'=>$mod);
			}
		}
		return $result;
	}
}

?>
