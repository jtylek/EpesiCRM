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
	public static function set_user($a) {
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
}

Acl::$gacl = new gacl_api(array('db'=>& DB::$ado));

?>
