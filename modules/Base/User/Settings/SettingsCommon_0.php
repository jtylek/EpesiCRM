<?php
/**
 * User_Settings class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user-settings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_SettingsCommon extends ModuleCommon {
	private static $admin_variables;
	private static $user_variables;

	public static function menu(){
		if (!Acl::is_user()) return array();
		return array('My settings'=>array('__weight__'=>10,'__submenu__'=>1,'Control panel'=>array()));
	}

	public static function body_access() {
		return Acl::is_user();
	}

	public static function admin_access() {
		return self::Instance()->acl_check('set defaults');
	}

	public static function admin_caption() {
		return 'Default user settings';
	}

	/**
	 * Returns default setting.
	 *
	 * @param string module name
	 * @param string variable name
	 * @return mixed variable value
	 */
	public static function get_default($module,$name){
		$module = str_replace('/','_',$module);
		static $variables;
		if (isset($variables[$module])) {
			if(isset($variables[$module][$name]))
				return $variables[$module][$name];
			return null;
		}
		if(method_exists($module.'Common', 'user_settings')) {
			$menu = call_user_func(array($module.'Common','user_settings'));
			if(is_array($menu))
				foreach($menu as $v) {
					if(!is_array($v)) continue;
					foreach($v as $v2) {
						if(!isset($v2['type'])) {
							return null;
							trigger_error('Type not defined in array: '.print_r($v2,true),E_USER_ERROR);
						}
						if ($v2['type']=='group') {
							foreach($v2['elems'] as $e)
								if ($e['type']!='static' && $e['type']!='header') {
									$variables[$module][$e['name']] = $e['default'];
									if($e['name']===$name)
										$ret=$e['default'];
								}
						} elseif ($v2['type']!='static' && $v2['type']!='header') {
							$variables[$module][$v2['name']] = $v2['default'];
							if($v2['name']===$name)
								$ret=$v2['default'];
						}
					}
				}
			if(isset($ret)) return $ret;
			return null;
		} else {
			return null;
			trigger_error('There is no common class for module: '.$module,E_USER_ERROR);
		}
	}

	/**
	 * Returns admin setting.
	 *
	 * @param string module name
	 * @param string variable name
	 * @return mixed user value
	 */
	public static function get_admin($module,$name){
		$module = str_replace('/','_',$module);
		if (!isset(self::$admin_variables)) {
			self::$admin_variables = array();
			$ret = DB::Execute('SELECT module,variable,value FROM base_user_settings_admin_defaults');
			while($row = $ret->FetchRow())
				self::$admin_variables[$row['module']][$row['variable']] = unserialize($row['value']);
		}
		if (isset(self::$admin_variables[$module][$name]))
			return self::$admin_variables[$module][$name];
		return self::$admin_variables[$module][$name] = self::get_default($module,$name);
	}

	/**
	 * Returns user setting.
	 * If user is logged in, returns user prefered setting,
	 * otherwise returns default value.
	 *
	 * @param string module name
	 * @param string variable name
	 * @return mixed user value
	 */
	public static function get($module,$name,$user=null){
		if (!Acl::is_user()) return null;
		if ($user===null) $user = Acl::get_user();
		$module = str_replace('/','_',$module);
		if (!isset(self::$user_variables[$user])) {
			self::$user_variables[$user] = array();
			$ret = DB::Execute('SELECT variable, value, module FROM base_user_settings WHERE user_login_id=%d',array($user));
			while($row = $ret->FetchRow())
				self::$user_variables[$user][$row['module']][$row['variable']] = unserialize($row['value']);
		}
		if (isset(self::$user_variables[$user][$module][$name]))
			return self::$user_variables[$user][$module][$name];
		return self::$user_variables[$user][$module][$name] = self::get_admin($module,$name);
	}

	/**
	 * Sets user setting to given value for currently logged in user.
	 * Returns false if no user is logged in.
	 *
	 * @param string module name
	 * @param string variable name
	 * @param mixed value
	 * @return bool true on success, false otherwise
	 */
	public static function save($module,$name,$value,$user=null){
		if (!Acl::is_user()) return false;
		//if ($value === null) $value = 0;
		$module = str_replace('/','_',$module);
		$def = self::get_admin($module,$name);
//		if (!isset($def)) return false;
		if (!Acl::is_user()) return null;
		if ($user===null) $user = Acl::get_user();
		if ($value==$def) {
			DB::Execute('DELETE FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Acl::get_user(),$module,$name));
			if(isset(self::$user_variables[$user])) unset(self::$user_variables[$user][$module][$name]);
		} else {
			if(isset(self::$user_variables[$user])) self::$user_variables[$user][$module][$name]=$value;
			$value = serialize($value);
			$val = DB::GetOne('SELECT value FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Acl::get_user(),$module,$name));
			if ($val === false || $val===null)
				DB::Execute('INSERT INTO base_user_settings VALUES (%d,%s,%s,%s)',array(Acl::get_user(),$module,$name,$value));
			else
				DB::Execute('UPDATE base_user_settings SET value=%s WHERE user_login_id=%d AND module=%s AND variable=%s',array($value,Acl::get_user(),$module,$name));
		}
		return true;
	}

	/**
	 * Sets admin setting to given value for currently logged in user.
	 * Returns false on permission denied.
	 *
	 * @param string module name
	 * @param string variable name
	 * @param mixed value
	 * @return bool true on success, false otherwise
	 */
	public static function save_admin($module,$name,$value){
		if (!self::Instance()->acl_check('set defaults')) return false;
		//if ($value === null) $value = 0;
		$module = str_replace('/','_',$module);
		$def = self::get_default($module,$name);
		if (!isset($def)) return false;
		if ($value==$def) {
			DB::Execute('DELETE FROM base_user_settings_admin_defaults WHERE module=%s AND variable=%s',array($module,$name));
			if(isset(self::$admin_variables)) unset(self::$admin_variables[$module][$name]);
		} else {
			if(isset(self::$admin_variables)) self::$admin_variables[$module][$name]=$value;
			$value = serialize($value);
			$val = DB::GetOne('SELECT value FROM base_user_settings_admin_defaults WHERE module=%s AND variable=%s',array($module,$name));
			if ($val === false || $val===null)
				DB::Execute('INSERT INTO base_user_settings_admin_defaults VALUES (%s,%s,%s)',array($module,$name,$value));
			else
				DB::Execute('UPDATE base_user_settings_admin_defaults SET value=%s WHERE module=%s AND variable=%s',array($value,$module,$name));
		}
		return true;
	}
}

?>
