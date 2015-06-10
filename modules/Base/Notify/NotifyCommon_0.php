<?php
/**
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 2.0
 * @package epesi-notify
 * 
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_NotifyCommon extends ModuleCommon {
	//interval to poll for new notifications
	const refresh_rate = 30; //seconds

	//interval at which to look back in time
	const reset_time = 24; //hours

	//messages to retrive with one refresh
	const message_refresh_limit = 3; //messages
	
	public static function init() {
		load_js('modules/Base/Notify/js/desktop-notify.js');
		load_js('modules/Base/Notify/js/main.js');

		$disabled_message = __('Notifications disabled or not supported!').'\n'.__('Check your browser settings and allow notifications to use this feature...');
		
		eval_js_once("Base_Notify.init (".(self::refresh_rate*1000).", '$disabled_message');");

		eval_js("Base_Notify.refresh (1);");
	}
	
	public static function init_session(&$request_token) {
		$new_instance = false;
		
		if (empty($request_token) && !READ_ONLY_SESSION) {
			$request_token = self::init_notified_cache();

			$new_instance = true;			
		}
		elseif (!self::set_user($request_token)) {
			$request_token = 0;
			
			$new_instance = true;
		}
		
		return $new_instance;
	}	
	
	public static function init_notified_cache() {
		$user = Acl::get_user();
	
		if (empty($user)) return 0;
		
		DB::Execute('DELETE FROM base_notify WHERE last_refresh < %d',array(strtotime('-24 hours')));
		
		$session_token = self::get_session_token();

		$exists = DB::GetOne('SELECT COUNT(*) FROM base_notify WHERE token=%s',array($session_token));
		if (!$exists) {
			DB::Execute('INSERT INTO base_notify (user_id, token, cache) VALUES (%d, %s, %s)',array($user, $session_token, self::serialize(array())));
		}		
		
		return $session_token;
	}
	
	public static function is_disabled() {
		return self::get_general_setting() == -1;
	}
	
	public static function is_refresh_due($token) {
		return time() >= Base_NotifyCommon::get_last_refresh($token) + Base_NotifyCommon::refresh_rate;
	}
	
	public static function set_user($token) {
		$user = DB::GetOne('SELECT user_id FROM base_notify WHERE token=%s',array($token));
	
		if (is_null($user)) return false;
	
		Acl::set_user($user);
	
		return true;
	}
	
	public static function check_user($token) {
		$user = DB::GetOne('SELECT user_id FROM base_notify WHERE token=%s',array($token));
	
		return $user == Acl::get_user();
	}
		
	public static function set_notified_cache($cache, $token, $refresh_time) {
		if (empty($cache)) {
			return DB::Execute('UPDATE base_notify SET last_refresh=%d WHERE token=%s',array($refresh_time, $token));;
		}
		
		$saved_cache = self::get_notified_cache($token);
	
		if (empty($saved_cache)) $saved_cache = array();
	
		$modules = array_merge(array_keys($cache), array_keys($saved_cache));
			
		foreach ($modules as $m) {
			$saved_ids = isset($saved_cache[$m])? $saved_cache[$m]:array();
			$new_ids = isset($cache[$m])? $cache[$m]:array();
	
			$ret[$m] = array_unique(array_merge($saved_ids, $new_ids));
		}
	
		return DB::Execute('UPDATE base_notify SET cache=%s, last_refresh=%d WHERE token=%s',array(self::serialize($ret), $refresh_time, $token));
	}
	
	public static function get_notified_cache($token) {
		static $cache;
	
		if (!isset($cache)) {
			$notified = DB::GetOne('SELECT cache FROM base_notify WHERE token=%s',array($token));
	
			if (!isset($notified)) {
				$notified = self::serialize(array());
			}
			$cache = self::unserialize($notified);
		}
	
		return $cache;
	}	

	public static function get_session_token() {
		if (!isset($_SESSION['base_notify_token']))			
			$_SESSION['base_notify_token'] = md5(microtime(true));
		
		return $_SESSION['base_notify_token'];
	}
	
	public static function clear_session_token() {
		unset($_SESSION['base_notify_token']);
	}
	
	public static function get_notifications($token) {
		$ret = array();

        $last_refresh = max(self::get_last_refresh($token), time() - self::reset_time * 3600);

        $notification_modules = ModuleManager::check_common_methods('tray_notification');
        
        foreach ($notification_modules as $module) {
        	$timeout = self::get_module_setting($module);
        	if ($timeout == -1) continue;
        	
        	$notify = call_user_func(array($module, 'tray_notification'), $last_refresh);
        	
        	if (!isset($notify['tray'])) continue;
        	
        	$new_module_notifications = self::filter_new_notifications($module, $notify['tray'], $token);
        	
        	if (empty($new_module_notifications)) continue;
        	
        	$ret[$module] = $new_module_notifications;
        }
              		
		return $ret;
	}	

	public static function filter_new_notifications($module, $all_messages, $token) {
		$notified_cache = self::get_notified_cache($token);

		if (empty($notified_cache[$module])) return $all_messages;
	
		$notified_messages = array_fill_keys($notified_cache[$module], 1);
	
		return array_diff_key($all_messages, $notified_messages);
	}	
	
	public static function get_last_refresh($token) {
		$ret = DB::GetOne('SELECT last_refresh FROM base_notify WHERE token=%s',array($token));
	
		return is_numeric($ret)? $ret: 0;
	}

	public static function get_general_setting() {
		static $cache;
		
		if (!isset($cache)) $cache = Base_User_SettingsCommon::get('Base_Notify', 'general_timeout');
		
		return $cache;
	}

	public static function get_module_setting($module) {
		static $cache;		
		
		$module = rtrim($module);
		
		if (!isset($cache[$module])) {
			$module_setting = Base_User_SettingsCommon::get('Base_Notify', $module.'_timeout');

			$cache[$module] = ($module_setting == -2) ? self::get_general_setting(): $module_setting;
		}
		
		return $cache[$module];
	}	

	public static function user_settings($settings_edit = false){
		if ($settings_edit)
			Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file('Base_Notify', 'icon.png'),__('Browser Settings'), 'onClick="Base_Notify.notify (\'Notification\', {body: \'enabled\', icon: \''.self::get_icon('Base_Notify').'\'}, true);"', __('Click to set browser settings for tray notifications'));

		$ret = array(
				array('name'=>null,'label'=>__('General'),'type'=>'header'),
				array('name'=>'general_timeout', 'reload'=>1, 'label'=>__('Close Message Timeout'),'type'=>'select','values'=>Utils_CommonDataCommon::get_translated_array('Base_Notify/Timeout', true),'default'=>0),
				array('name'=>'general_group','label'=>__('Group Similar Notifications'),'type'=>'checkbox','default'=>1),
	
				array('name'=>null,'label'=>__('Module Specific Timeout'),'type'=>'header')
		);
	
		$modules = ModuleManager::check_common_methods('tray_notification');
	
		foreach ($modules as $module) {
			$label = self::get_module_caption($module);
	
			$ret = array_merge($ret, array(array('name'=>$module.'_timeout','label'=>$label,'type'=>'select','values'=>array(-2=>_M('Use general setting')) + Utils_CommonDataCommon::get_translated_array('Base_Notify/Timeout', true),'default'=>-2)));
		}
	
		return array(__('Notify')=>$ret);
	}		

	public static function group_similar() {
		return Base_User_SettingsCommon::get('Base_Notify', 'general_group')==1;
	}	

	public static function get_module_caption($module) {
		$module = rtrim($module, 'Common');
		if (is_callable($module.'Common::caption')) {
			$caption = call_user_func($module.'Common::caption');
		}
		elseif (is_callable($module.'Common::applet_caption')) {
			$caption = call_user_func($module.'Common::applet_caption');
		}
		else $caption = $module;
	
		return $caption;
	}	

	public static function get_icon($module, $message = null) {
		$icon = Base_ThemeCommon::get_template_file($module, isset($message['icon']) ? $message['icon']:'icon.png');
		return isset($icon)? $icon: Base_ThemeCommon::get_template_file('Base_Notify', 'icon.png');
	}
	
	public static function strip_html ($text) {
		return str_replace('&nbsp;',' ',htmlspecialchars_decode(strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/',"\n",$text))));
	}
	
	public static function serialize($txt) {
		$serialized = serialize($txt);
		$compressed = function_exists('gzcompress')? gzcompress($serialized): $serialized;
		return base64_encode($compressed);
	}
	
	public static function unserialize($txt) {
		$decoded = base64_decode($txt);
		$uncompressed = function_exists('gzuncompress')? gzuncompress($decoded): $decoded;
		return @unserialize($uncompressed);
	}
}

on_init(array('Base_NotifyCommon', 'init'));

?>