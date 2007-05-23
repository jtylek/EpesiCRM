<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence TL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

define('ADODB_DIR','adodb');

require_once('phpgacl/gacl.class.php');
require_once('phpgacl/gacl_api.class.php');

class Acl {
    public static $gacl;
    
    public static function check($a,$b,$c,$d) {
	if(!isset($d)) {
	    $d = self::get_user();
	    if(!isset($d))
		return false;
	}
	if(!isset($c)) $c = 'Users';
	return self::$gacl->acl_check($a,$b,$c,$d);
    }
    
    public static function get_user() {
	return $_SESSION[$_SERVER['PHP_SELF']]['user'];
    }
    
    public static function set_user($a) {
	if(isset($a))
	    $_SESSION[$_SERVER['PHP_SELF']]['user'] = $a;
	else
	    unset($_SESSION[$_SERVER['PHP_SELF']]['user']);
    }
    
    public static function is_user() {
	return isset($_SESSION[$_SERVER['PHP_SELF']]['user']);
    }
}

Acl::$gacl = new gacl_api(array('db'=>& DB::$ado));

?>
