<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

defined('ADODB_DIR') || define('ADODB_DIR','adodb');

require_once('phpgacl/gacl.class.php');
require_once('phpgacl/gacl_api.class.php');

class Acl {
	public static $gacl;
    
	/**
	 * Wrapper to acl_check phpgacl method.
	 * Third and fourth argument can be omited, it will be replaced by currently logged user. Be careful: anonymous user always returns false.
	 * 
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @return bool false on access denied
	 */
	public static function check($a,$b,$c=null,$d=null) {
		if(!isset($d)) {
			$d = self::get_user();
			if(!isset($d))
				return false;
		}
		if(!isset($c)) $c = 'Users';
		return self::$gacl->acl_check($a,$b,$c,$d);
	}
	
	/**
	 * Get currently logged user.
	 * 
	 * @return string
	 */
	public static function get_user() {
		return isset($_SESSION[$_SERVER['PHP_SELF']]['user'])?$_SESSION[$_SERVER['PHP_SELF']]['user']:null;
	}
    
    	/**
	 * Set currently logged user
	 */
	public static function set_user($a=null) {
		if(isset($a))
			$_SESSION[$_SERVER['PHP_SELF']]['user'] = $a;
		else
			unset($_SESSION[$_SERVER['PHP_SELF']]['user']);
	}
    
    	/**
	 * Are you logged?
	 *
	 * @return bool 
	 */
	public static function is_user() {
		return isset($_SESSION[$_SERVER['PHP_SELF']]['user']);
	}
	
	/**
	 * Add group of users
	 * @param array recurence: key-group name, value-groups dependent
	 * @param int parent
	 */
	public static function add_groups($names, $parent=null) {
		if($parent===null) $parent = Acl::$gacl->get_group_id('User');
		if(is_array($names)) {
			foreach($names as $k=>$v) {
				if(is_array($v) && is_string($k)) {
					print('1) addding group: '.$k.'<br>');
					self::add_groups($v,self::$gacl->add_group($k,$k, $parent));
				} elseif(is_string($v)) {
					print('2) addding group: '.$v.'<br>');
					if(!self::$gacl->add_group($v,$v, $parent)) return false;
				} else 
					return false;
			}
		} elseif(is_string($names)) {
			print('3) addding group: '.$names.'<br>');
			self::$gacl->add_group($names,$names, $parent);
		} else 
			return false;
		return true;
	}
	
	public static function del_group($name) {
		$id = Acl::$gacl->get_group_id($name);
		if($id===false)
			return false;
		
		Acl::$gacl->del_group($id);
		return true;
	}
	
	public static function add_aco($section,$name) {
		$id = Acl::$gacl->get_object_section_section_id($section,$section,'aco');
		if($id===false)
			Acl::$gacl->add_object_section($section,$section,0,0,'aco');
		return Acl::$gacl->add_object($section,$name,$name,0,0,'aco');
	}
	
	public static function del_aco_section($section) {
		$id = Acl::$gacl->get_object_section_section_id($section,$section,'aco');
		if($id===false)
			return false;
		return Acl::$gacl->del_object_section($id, 'aco',true);
	}
	
	public static function aco_accept_group($section,$name,$group) {
		$x = Acl::$gacl->get_group_id($group);
		if($x===false) return false;
		Acl::$gacl->add_acl(array($section =>array($name)), array(), array($x), NULL, NULL,1,1,'','','user');
		return true;
	}
}

Acl::$gacl = new gacl_api(array('db'=>& DB::$ado));

?>
