<?php
/**
 * User class.
 *
 * The functions of this class facilitate user management.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage user
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_UserCommon extends ModuleCommon {
	/**
	 * Changes state of user (active or inactive).
	 *
	 * @param integer user id
	 * @param bool is active?
	 */
	public static function change_active_state($uid, $active) {
		return DB::Execute('UPDATE user_login SET active=%b WHERE id=%d',array($active, $uid));
	}

	/**
	 * Adds user to the database and adds to User group (normal, regular user).
	 *
	 * @param string username
	 * @return bool true on success, false otherwise
	 */
	public static function add_user($username) {
		if(DB::Execute('INSERT INTO user_login(login) VALUES(%s)', $username)===false) {
			print('Unable to add user to user_login table<br>');
			return false;
		}
		$acl = Base_AclCommon::add_user($username);
		if(!$acl) {
			print('Unable to add user to ACL. Deleting user.');
			DB::Execute('DELETE FROM user_login WHERE login=%s', array($username));
		}
		return $acl;
	}

	/**
	 * Returns user id.
	 *
	 * @param string username
	 * @return integer user id
	 */
	public static function get_user_id($username) {
		return DB::GetOne('SELECT id FROM user_login WHERE login=%s', array($username));
	}

	/**
	 * Returns user username.
	 *
	 * @param integer user id
	 * @return string username
	 */
	public static function get_user_login($id) {
		return DB::GetOne('SELECT login FROM user_login WHERE id=%d', array($id));
	}

	/**
	 * Returns id of currently logged in user.
	 * Method returns null if no user is logged in.
	 *
	 * @return mixed user id
	 */
	public static function get_my_user_id() {
		if(Acl::is_user()) {
		    if(!isset($_SESSION['client']['user_id'])) {
				$id = self::get_user_id(Acl::get_user());
				$_SESSION['client']['user_id'] = $id;
		    }
		} else {
			unset($_SESSION['client']['user_id']);
		}
		return $_SESSION['client']['user_id'];
    }

	/**
	 * For internal use only.
	 */
	public static function set_my_user_id($a=null) {
		if(isset($a))
			$_SESSION['client']['user_id'] = $a;
		else
			unset($_SESSION['client']['user_id']);
        }
}

?>
