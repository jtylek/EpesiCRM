<?php
/**
 * User class.
 * 
 * The functions of this class facilitate user management.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * The functions of this class facilitate user management.
 * @package epesi-base-extra
 * @subpackage user
 */
class Base_UserCommon {
	/**
	 * Change state of user (active or inactive).
	 * 
	 * @param integer user id
	 * @param bool active?
	 */
	public static function change_active_state($uid, $active) {
		return DB::Execute('UPDATE user_login SET active=%b WHERE id=%d',array($active, $uid));
	}
	
	/**
	 * Add user to database and add to User group (normal, regular user).
	 * 
	 * @param string username
	 * @return bool everything went ok?
	 */
	public static function add_user($username) {
		if(DB::Execute('INSERT INTO user_login(login) VALUES(%s)', $username)===false) {
			print('Unable to add user to user_login table<br>');
			return false;
		}
		$acl = Base_AclCommon::add_user($username);
		if(!$acl) {
			print('Unable to add user to ACL. Deleting user.');
			DB::Execute('DELETE FROM user_login WHERE login=%s',$username);
		}
		return $acl;
	}
	
	/**
	 * Get user id.
	 * 
	 * @param string username
	 * @return integer user id
	 */
	public static function get_user_id($username) {
		return DB::GetOne('SELECT id FROM user_login WHERE login=%s', $username);
	}
	
	public static function get_user_login($id) {
		return DB::GetOne('SELECT login FROM user_login WHERE id=%d', $id);
	}
	
	public static function get_my_user_id() {
    		global $base;
		$session = & $base->get_session();
		$id = $session['user_id'];
		if(Acl::is_user()) {
		    if(!isset($id)) {
			$id = self::get_user_id(Acl::get_user());
			$session['user_id'] = $id;
		    }
		} else {
		    unset($id);
		    unset($session['user_id']);
		}
		return $id;
    	}
	
	public static function set_my_user_id($a) {
    		global $base;
		$session = & $base->get_session();
		if(isset($a))
		        $session['user_id'] = $a;
		else
		        unset($session['user_id']);
        }
}

?>
