<?php
/**
 * AclInit class.
 * 
 * This class provides initialization data for Acl module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage acl
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AclCommon extends ModuleCommon {
	public static function admin_caption() {
		return array('label'=>__('Access Restrictions'), 'section'=>__('User Management'));
	}

	public static function get_admin_level($user = null) {
		if ($user === null) $user = self::get_user();
		$admin = @DB::GetRow('SELECT * FROM user_login WHERE id=%d', array($user));
		if ($admin && !empty($admin) && !isset($admin['admin'])) return 2;
		else $admin = isset($admin['admin'])?$admin['admin']:0;
		return $admin;
	}
	
	/**
	 * Return if user calling this function is Super Administrator.
	 * 
	 * @return bool
	 */
	public static function i_am_sa() {
		static $ret, $user;
		$new_user = self::get_user();
		if (!isset($ret) || $new_user != $user) { 
			$user = $new_user;
			$ret = (Variable::get('anonymous_setup') || self::get_admin_level()>=2);
		}
		return $ret;
	}
	
	/**
	 * Returns whether currently logged in user is an administrator.
	 * 
	 * @return bool true if currently logged in user is an administrator
	 */
	public static function i_am_admin() {
		static $ret, $user;
		$new_user = self::get_user();
		if (!isset($ret) || $new_user != $user) { 
			$user = $new_user;
			$ret = (Variable::get('anonymous_setup') || self::get_admin_level()>=1);
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
		if (self::$cached_user==false) self::$cached_user = isset($_SESSION['user'])?$_SESSION['user']:null;
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
    
    	/**
	 * Are you logged?
	 *
	 * @return bool 
	 */
	public static function is_user() {
		return self::get_user()!==null;
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
		if (!isset($cache[$all])) {
			$ret = DB::Execute('SELECT * FROM base_acl_clearance');
			$clearance = array();
			while ($row = $ret->FetchRow()) {
				$callback = explode('::', $row['callback']);
				$new = call_user_func($callback, $all);
				$clearance = array_merge($clearance, $new);
			}
			$cache[$all] = $clearance;
		}
		return $cache[$all];
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
		if ($ids) return true;
		else return false;
	}
}

abstract class Acl extends Base_AclCommon {}

?>
