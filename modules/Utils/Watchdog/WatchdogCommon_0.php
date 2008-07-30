<?php
/**
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Arkadiusz Bisaga <abisaga@telaxus.com>
 * @license SPL
 * @version 0.1
 * @package utils-watchdog
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_WatchdogCommon extends ModuleCommon {
	private static function get_category_id($category_name) {
		return DB::GetOne('SELECT id FROM utils_watchdog_category WHERE name=%s', array(md5($category_name)));
	}
	public static function register_category($category_name, $callback) {
		$exists = DB::GetOne('SELECT name FROM utils_watchdog_category WHERE name=%s',array(md5($category_name)));
		if ($exists!==false) return;
		if (is_array($callback)) $callback = implode('::',$callback);
		DB::Execute('INSERT INTO utils_watchdog_category (name, callback) VALUES (%s,%s)',array(md5($category_name),$callback));
	}

	public static function unregister_category($category_name) {
		$category_id = self::get_category_id($category_name);
		DB::Execute('DELETE FROM utils_watchdog_category WHERE name=%s',array(md5($category_name)));
		DB::Execute('DELETE FROM utils_watchdog_category_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_event WHERE category_id=%d',array($category_id));
	}

	public static function new_event($category_name, $id, $message) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		DB::Execute('INSERT INTO utils_watchdog_event (category_id, internal_id, message) VALUES (%d,%d,%s)',array($category_id,$id,$message));
		//TODO: notify those subscribed to the category
	}

	public static function user_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===null) $last_event = -1;
		DB::Execute('UPDATE utils_watchdog_subscription SET last_seen_event=%d WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($last_event,$user_id,$id,$category_id));
	}

	public static function user_subscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		$already_subscribed = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($already_subscribed===false) DB::Execute('INSERT INTO utils_watchdog_subscription (last_seen_event, user_id, internal_id, category_id) VALUES (%d,%d,%d,%d)',array(-1,$user_id,$id,$category_id));
	}

	public static function user_unsubscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		DB::Execute('DELETE FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
	}

	public static function check_if_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		$last_seen = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($last_seen===false) return null;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===null) $last_event=-1;
		return $last_seen==$last_event;
	}
	
	public static function notified($category_name, $id) {
		self::user_notified(Acl::get_user(), $category_name, $id);
	}

	public static function subscribe($category_name, $id) {
		self::user_subscribe(Acl::get_user(), $category_name, $id);
	}

	public static function unsubscribe($category_name, $id) {
		self::user_unsubscribe(Acl::get_user(), $category_name, $id);
	}
	
}

?>