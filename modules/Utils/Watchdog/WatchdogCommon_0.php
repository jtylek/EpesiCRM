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
	public static function applet_caption() {
		return "Subscriptions";
	}
	public static function applet_info() {
		return "Helps tracking changes introduced in the system";
	}
	public static function get_subscribers($category_name, $id) {
		$category_id = self::get_category_id($category_name);
		$ret = DB::GetAssoc('SELECT user_id,user_id FROM utils_watchdog_subscription WHERE category_id=%d AND internal_id=%s', array($category_id, $id));
		return $ret;
	}
	public static function get_category_id($category_name) {
		static $cache = array();
		if (isset($cache[$category_name])) return $cache[$category_name];
		$ret = DB::GetOne('SELECT id FROM utils_watchdog_category WHERE name=%s', array(md5($category_name)));
		if (!$ret && is_numeric($category_name)) return $category_name;  
		return $cache[$category_name] = $ret;
	}
	private static function check_if_user_subscribes($user, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_seen = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user,$id,$category_id));
		return ($last_seen!==false && $last_seen!==null);
	}
	// ****************** registering ************************
	public static function register_category($category_name, $callback) {
		$exists = DB::GetOne('SELECT name FROM utils_watchdog_category WHERE name=%s',array(md5($category_name)));
		if ($exists!==false) return;
		if (is_array($callback)) $callback = implode('::',$callback);
		DB::Execute('INSERT INTO utils_watchdog_category (name, callback) VALUES (%s,%s)',array(md5($category_name),$callback));
	}

	public static function unregister_category($category_name) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		DB::Execute('DELETE FROM utils_watchdog_category WHERE name=%s',array(md5($category_name)));
		DB::Execute('DELETE FROM utils_watchdog_category_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_event WHERE category_id=%d',array($category_id));
	}
	// *********************************** New event ***************************
	public static function new_event($category_name, $id, $message) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		DB::Execute('INSERT INTO utils_watchdog_event (category_id, internal_id, message) VALUES (%d,%d,%s)',array($category_id,$id,$message));
		//TODO: notify those subscribed to the category
	}
	// *************************** Subscription manipulation *******************
	public static function user_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===null) $last_event = -1;
		DB::Execute('UPDATE utils_watchdog_subscription SET last_seen_event=%d WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($last_event,$user_id,$id,$category_id));
	}

	public static function user_subscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$already_subscribed = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($already_subscribed===false) DB::Execute('INSERT INTO utils_watchdog_subscription (last_seen_event, user_id, internal_id, category_id) VALUES (%d,%d,%d,%d)',array(-1,$user_id,$id,$category_id));
		if ($user_id==Acl::get_user()) self::notified($category_name, $id);
	}

	public static function user_unsubscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		DB::Execute('DELETE FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
	}

	public static function user_check_if_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_seen = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($last_seen===false || $last_seen===null) return null;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===false || $last_event===null) $last_event=-1;
		if ($last_seen==$last_event) return true;
		$ret = array();
		$missed_events = DB::Execute('SELECT id,message FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d AND id>%d ORDER BY id ASC', array($id,$category_id,$last_seen));
		while ($row = $missed_events->FetchRow())
			$ret[$row['id']] = $row['message'];
		return $ret;
	}
	
	public static function user_get_change_subscr_href($user, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$subscribed = self::check_if_user_subscribes($user, $category_name, $id);
		if (isset($_REQUEST['utils_watchdog_category']) &&
			isset($_REQUEST['utils_watchdog_user']) &&  
			isset($_REQUEST['utils_watchdog_id']) &&
			$_REQUEST['utils_watchdog_category']==$category_id &&
			$_REQUEST['utils_watchdog_user']==$user &&  
			$_REQUEST['utils_watchdog_id']==$id) {
			if ($subscribed) self::user_unsubscribe($user, $category_name, $id);	
			else self::user_subscribe($user, $category_name, $id);
			location(array());	
		}
		return Module::create_href(array('utils_watchdog_category'=>$category_id, 'utils_watchdog_user'=>$user, 'utils_watchdog_id'=>$id));
	}
	// **************** Subscription manipulation for logged user *******************
	public static function notified($category_name, $id) {
		self::user_notified(Acl::get_user(), $category_name, $id);
	}
	public static function subscribe($category_name, $id) {
		self::user_subscribe(Acl::get_user(), $category_name, $id);
	}
	public static function unsubscribe($category_name, $id) {
		self::user_unsubscribe(Acl::get_user(), $category_name, $id);
	}
	public static function check_if_notified($category_name, $id) {
		return self::user_check_if_notified(Acl::get_user(), $category_name, $id);
	}
	public static function get_change_subscr_href($category_name, $id) {
		return self::user_get_change_subscr_href(Acl::get_user(), $category_name, $id);
	}
	public static function add_actionbar_change_subscription_button($category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$href = self::get_change_subscr_href($category_name, $id);
		$subscribed = self::check_if_user_subscribes(Acl::get_user(), $category_id, $id);
		if ($subscribed) {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','unsubscribe_big.png');
			$label = Base_LangCommon::ts('Utils_Watchdog','Unsubscribe');
		} else {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','subscribe_big.png');
			$label = Base_LangCommon::ts('Utils_Watchdog','Subscribe');
		}
		Base_ActionBarCommon::add($icon,$label,$href);
	}
	public static function implode_events($arr) {
		$i = -1;
		$last_message = '';
		$counts = array();
		foreach ($arr as $k=>$v) {
			if ($last_message!=$v) {
				$last_message=$v;
				$i = $k;
				$counts[$i] = 1;
				continue;
			}
			unset($arr[$k]);
			$counts[$i]++;
		}
		foreach ($arr as $k=>$v) {
			if ($counts[$k]!=1) $arr[$k] = $v.' <i>x'.$counts[$k].'</i>';
		}
		return implode('<br>',$arr);
	} 
	public static function get_change_subscription_icon($category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$href = self::get_change_subscr_href($category_name, $id);
		$last_seen = self::check_if_notified($category_name, $id);
		if ($last_seen===null) {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','subscribe_small.png');
			$tooltip = Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_Watchdog','Click to subscribe this record.'));
		} else {
			if ($last_seen===true) {
				$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','unsubscribe_small.png');
				$tooltip = Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_Watchdog','You are subscribing this record. Click to unsubscribe.'));
			} else {
				$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','unsubscribe_small_new_events.png');
				$tooltip = Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_Watchdog','You are subscribing this record. Click to unsubscribe.<br>The following events ocurred since the last time you were viewing this record:<br>%s',array(self::implode_events($last_seen))));
			}
		}
		return '<a '.$href.' '.$tooltip.'><img border="0" src="'.$icon.'"></a>';
	} 
}

?>