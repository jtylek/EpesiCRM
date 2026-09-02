<?php
/**
 * AclInit class.
 * 
 * This class provides initialization data for Acl module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage acl
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AclCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-person-gear'; }

	public static function admin_caption() {
		return array('label'=>__('Access Restrictions'), 'section'=>__('User Management'));
	}

    public static function admin_access() {
        return DEMO_MODE?false:true;
    }

	public static function get_admin_level($user = null) {
		if ($user === null) $user = self::get_user();
		$admin = @DB::GetRow('SELECT * FROM user_login WHERE id=%d', array($user));
		if ($admin && !empty($admin) && !isset($admin['admin'])) return 2;
		else $admin = $admin['admin'] ?? 0;
		return $admin;
	}

	/**
	 * Is the "anonymous_setup" bootstrap bypass currently in effect?
	 *
	 * anonymous_setup makes every visitor - logged in or not - read as an
	 * administrator, so setup.php/FirstRun can install modules and write
	 * configuration before any account exists to authenticate as. It is set by
	 * Base_SetupInstall::install() and cleared by FirstRun once the real
	 * super-admin has been created.
	 *
	 * The flag on its own is NOT enough to grant the bypass. It only applies
	 * while there is genuinely no super-admin yet: once an admin=2 user
	 * exists the bootstrap window is over by definition, so a stale flag must
	 * not leave the install wide open forever. That failure mode was real -
	 * the demo:generate:* console commands used to set the flag and never
	 * restore it, which silently turned every visitor into a super-admin on
	 * any install where demo data had been generated.
	 *
	 * Order matters for cost: the flag is read first (Variable:: loads the
	 * whole table once per request, so this is free) and the admin-exists
	 * query only runs on an install that actually has the flag set.
	 *
	 * Variable::get()'s second argument suppresses NoSuchVariableException so
	 * a missing row reads as "off" - never as "on", which is how
	 * SimpleLogin::form()'s own catch block used to treat it.
	 *
	 * Read this rather than Variable::get('anonymous_setup') directly.
	 *
	 * @return bool
	 */
	public static function anonymous_setup_active() {
		static $active;
		if (!isset($active))
			$active = (bool) Variable::get('anonymous_setup', false)
				&& !DB::GetOne('SELECT id FROM user_login WHERE admin=2');
		return $active;
	}

	/**
	 * Explicit, process-local elevation for the one install step that runs
	 * before any account exists.
	 *
	 * i_am_sa()/i_am_admin() deliberately do NOT consult anonymous_setup any
	 * more: a DB flag is inheritable by any incoming request, which is what
	 * made those two helpers untrustworthy as an access gate in the first
	 * place. This property is the replacement, and it is a different kind of
	 * thing - it lives only in the running process, is never persisted, and is
	 * set from exactly one place (FirstRun::done(), around
	 * ModuleManager::install('Base'), the only install that happens before the
	 * super-admin exists). No HTTP request can turn it on.
	 *
	 * It stays set until end_bootstrap_install() or the end of the request,
	 * whichever comes first - FirstRun's own early `return false` paths abort
	 * the wizard anyway, and nothing survives the request.
	 *
	 * Do not add a second caller without a very good reason. If you need
	 * "is the install still being bootstrapped?" for a UI gate rather than for
	 * an install step, that is anonymous_setup_active(), not this.
	 */
	private static $bootstrap_install = false;

	public static function begin_bootstrap_install() { self::$bootstrap_install = true; }
	public static function end_bootstrap_install()   { self::$bootstrap_install = false; }

	/**
	 * Breadcrumb for the one thing that could plausibly go wrong with the
	 * change above: an install-time code path that used to ride on
	 * anonymous_setup and is now denied.
	 *
	 * Only ever fires inside a genuine bootstrap window (flag set, no
	 * super-admin yet) with no elevation active - so it is silent on every
	 * normal install, and writes at most one line per request. If a fresh
	 * install ever misbehaves, firstrun.log says so explicitly instead of
	 * leaving a silent permission denial to be guessed at.
	 */
	private static function log_bootstrap_denial($what) {
		static $logged = false;
		if ($logged || self::$bootstrap_install || !self::anonymous_setup_active()) return;
		$logged = true;
		if (function_exists('epesi_log'))
			epesi_log(date('Y-m-d H:i:s') . ': ' . $what
				. '() denied during the anonymous_setup bootstrap window, with no bootstrap'
				. " elevation active. If a fresh install misbehaves, start here.\n", 'firstrun.log');
	}

	/**
	 * Return if user calling this function is Super Administrator.
	 * 
	 * @return bool
	 */
	public static function i_am_sa() {
		static $ret, $user, $boot;
		$new_user = self::get_user();
		if (!isset($ret) || $new_user != $user || $boot !== self::$bootstrap_install) {
			$user = $new_user;
			$boot = self::$bootstrap_install;
			$ret = ($boot || self::get_admin_level()>=2);
			if (!$ret) self::log_bootstrap_denial('i_am_sa');
		}
		return $ret;
	}
	
	/**
	 * Returns whether currently logged in user is an administrator.
	 * 
	 * @return bool true if currently logged in user is an administrator
	 */
	public static function i_am_admin() {
		static $ret, $user, $boot;
		$new_user = self::get_user();
		if (!isset($ret) || $new_user != $user || $boot !== self::$bootstrap_install) {
			$user = $new_user;
			$boot = self::$bootstrap_install;
			$ret = ($boot || self::get_admin_level()>=1);
			if (!$ret) self::log_bootstrap_denial('i_am_admin');
		}
		return $ret;
	}

	/**
	 * Returns whether currently logged in user is a user.
	 * 
	 * @return bool true if currently logged in user is a user
	 */
	public static function i_am_user() {
		return self::is_user();
	}
	/**
	 * Get currently logged user.
	 * 
	 * @return string
	 */
	private static $cached_user = false;
	public static function get_user() {
		if (self::$cached_user==false) self::$cached_user = $_SESSION['user'] ?? null;
		return self::$cached_user;
	}
    
   	/**
	 * Set currently logged user
	 */
	public static function set_user($a=null, $real=false) {
		self::$cached_user = $a;
		if (!$real) return;
		if(isset($a))
			$_SESSION['user'] = $a;
		else
			unset($_SESSION['user']);
	}
    
	public static function set_sa_user() {
		self::$cached_user = DB::GetOne('SELECT id FROM user_login WHERE admin=2');
	}
	
    	/**
	 * Are you logged?
	 *
	 * @return bool 
	 */
	public static function is_user() {
		return self::get_user()!==null;
	}
	
	public static function display_clearances($clearances) {
		$all_clearances = array_flip(Base_AclCommon::get_clearance(true));
		foreach ($clearances as $k=>$v)
			if (isset($all_clearances[$v])) $clearances[$k] = $all_clearances[$v];
			else unset($clearances[$k]);
		return '<span class="Base_Acl__permissions_clearance">'.implode(' <span class="joint">'.__('and').'</span> ',$clearances).'</span>';
	}
	
	public static function basic_clearance($all=false) {
		$user_clearance = array(__('All users')=>'ALL');
		if ($all || Base_AclCommon::i_am_admin()) $user_clearance[__('Admin')] = 'ADMIN';
		if ($all || Base_AclCommon::i_am_sa()) $user_clearance[__('Superadmin')] = 'SUPERADMIN';
		return $user_clearance;
	}
	public static function add_clearance_callback($callback) {
		if (is_array($callback)) $callback = implode('::', $callback);
		self::remove_clearance_callback($callback);
		DB::Execute('INSERT INTO base_acl_clearance (callback) VALUES (%s)', array($callback));
	}
	public static function remove_clearance_callback($callback) {
		if (is_array($callback)) $callback = implode('::', $callback);
		DB::Execute('DELETE FROM base_acl_clearance WHERE callback=%s', array($callback));
	}
	
	public static function get_clearance($all=false) {
		static $cache = array();
		if (!isset($cache[Acl::get_user()]) || !isset($cache[Acl::get_user()][$all])) {
			$ret = DB::Execute('SELECT * FROM base_acl_clearance');
			$clearance = array();
			while ($row = $ret->FetchRow()) {
				$callback = explode('::', $row['callback']);
				$new = call_user_func($callback, $all);
				$clearance = array_merge($clearance, $new);
			}
			$cache[Acl::get_user()][$all] = $clearance;
		}
		return $cache[Acl::get_user()][$all];
	}
	
	public static function add_permission($name) {
		$args = func_get_args();
		array_shift($args);
		$perm_id = DB::GetOne('SELECT id FROM base_acl_permission WHERE name=%s', array($name));
		if (!$perm_id) {
			DB::Execute('INSERT INTO base_acl_permission (name) VALUES (%s)', array($name));
			$perm_id = DB::Insert_ID('base_acl_permission', 'id');
		}
		foreach ($args as $rule) {
			DB::Execute('INSERT INTO base_acl_rules (permission_id) VALUES (%d)', array($perm_id));
			$rule_id = DB::Insert_ID('base_acl_rules', 'id');
			if (!is_array($rule)) $rule = array($rule);
			foreach ($rule as $clearance) {
				DB::Execute('INSERT INTO base_acl_rules_clearance (rule_id, clearance) VALUES (%d, %s)', array($rule_id, $clearance));
			}
		}
	}
	public static function delete_permission($name) {
		$perm_id = DB::GetOne('SELECT id FROM base_acl_permission WHERE name=%s', array($name));
		if (!$perm_id)
			return;
		DB::Execute('DELETE FROM base_acl_rules_clearance WHERE rule_id IN (SELECT id FROM base_acl_rules WHERE permission_id=%d)', array($perm_id));
		DB::Execute('DELETE FROM base_acl_rules WHERE permission_id=%d', array($perm_id));
		DB::Execute('DELETE FROM base_acl_permission WHERE id=%d', array($perm_id));
	}
	public static function check_permission($name) {
		static $cache = array();
		if (isset($cache[Acl::get_user()]) && isset($cache[Acl::get_user()][$name])) return $cache[Acl::get_user()][$name];
		$perm_id = DB::GetOne('SELECT id FROM base_acl_permission WHERE name=%s', array($name));
		if (!$perm_id) return false;
		$clearance = self::get_clearance();

		$sql = 'SELECT id FROM base_acl_rules AS rule WHERE permission_id=%d';
		$vals = array($perm_id);
		if ($clearance!=null) {
			$sql .= ' AND NOT EXISTS (SELECT * FROM base_acl_rules_clearance WHERE rule_id=rule.id AND '.implode(' AND ',array_fill(0, count($clearance), 'clearance!=%s')).')';
			$vals = array_merge($vals, array_values($clearance));
		} else {
			$sql .= ' AND NOT EXISTS (SELECT * FROM base_acl_rules_clearance WHERE rule_id=rule.id)';
		}
		$ids = DB::GetOne($sql, $vals);
		if ($ids) return $cache[Acl::get_user()][$name] = true;
		else return $cache[Acl::get_user()][$name] = false;
	}
}

abstract class Acl extends Base_AclCommon {}

?>
