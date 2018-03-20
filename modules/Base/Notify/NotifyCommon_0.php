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
	const refresh_rate_telegram = 300; //seconds

	//interval at which to look back in time
	const reset_time = 24; //hours

	//messages to retrive with one refresh
	const message_refresh_limit = 3; //messages

    private static $initialized = false;
	
	public static function init() {
        if (Base_AclCommon::is_user() == false || self::$initialized) {
            return;
        }

        DB::Execute('DELETE FROM base_notify WHERE single_cache_uid is null AND last_refresh < %d',array(strtotime('-24 hours')));

        load_js('modules/Base/Notify/js/desktop-notify.js');
		load_js('modules/Base/Notify/js/main.js');

		$disabled_message = __('Notifications disabled!')."\n".__('Check your browser settings and allow notifications to use this feature...');
        $disabled_message = json_encode($disabled_message);
		eval_js_once("Base_Notify.init (".(self::refresh_rate*1000).", $disabled_message);");

        self::$initialized = true;
	}
	
	public static function is_disabled() {
		return self::get_general_setting() == -1;
	}
	
	public static function is_refresh_due($token) {
		return time() >= Base_NotifyCommon::get_last_refresh($token) + Base_NotifyCommon::refresh_rate;
	}

	public static function is_refresh_due_telegram($token) {
		return time() >= Base_NotifyCommon::get_last_refresh($token) + Base_NotifyCommon::refresh_rate_telegram;
	}

	public static function set_notified_cache($cache, $token, $refresh_time) {
		if (empty($cache)) {
			return DB::Execute('UPDATE base_notify SET last_refresh=%d WHERE token=%s AND (last_refresh<=%d OR last_refresh IS NULL)', array($refresh_time, $token, $refresh_time));
		}
		
		$saved_cache = self::get_notified_cache($token);
	
		if (empty($saved_cache)) $saved_cache = array();
	
		$modules = array_merge(array_keys($cache), array_keys($saved_cache));

        $ret = array();
		foreach ($modules as $m) {
			$saved_ids = isset($saved_cache[$m])? $saved_cache[$m]:array();
			$new_ids = isset($cache[$m])? $cache[$m]:array();
	
			$ret[$m] = array_unique(array_merge($saved_ids, $new_ids));
		}
	
		return DB::Execute('UPDATE base_notify SET cache=%s, last_refresh=%d WHERE token=%s AND (last_refresh<=%d OR last_refresh IS NULL)',array(self::serialize($ret), $refresh_time, $token, $refresh_time));
	}
	
	public static function get_notified_cache($token) {
		static $cache = array();
	
		if (!isset($cache[$token])) {
			$notified = DB::GetOne('SELECT cache FROM base_notify WHERE token=%s',array($token));
	
			if (!isset($notified)) {
				$notified = self::serialize(array());
			}
			$cache[$token] = self::unserialize($notified);
		}
	
		return $cache[$token];
	}	

	public static function get_session_token($one_cache=null) {
        $user_id = Base_AclCommon::get_user();
        if (!$user_id) return false;

		if($one_cache===null) $one_cache = Base_User_SettingsCommon::get('Base_Notify', 'one_cache');
		if($one_cache) {
            $token = DB::GetOne('SELECT token FROM base_notify WHERE single_cache_uid=%d',array(Base_AclCommon::get_user()));
            if($token) return $token;
        }

		$session_id = session_id();
        if (!$session_id) return false;
        $token = md5($user_id . '__' . $session_id);

		if($one_cache) {
			$exists = DB::GetOne('SELECT 1 FROM base_notify WHERE token=%s', array($token));
			if($exists) DB::Execute('UPDATE base_notify SET single_cache_uid=%d WHERE token=%s',array(Base_AclCommon::get_user(),$token));
			else DB::Execute('INSERT INTO base_notify (token, cache,single_cache_uid) VALUES (%s, %s, %d)', array($token, self::serialize(array()),Base_AclCommon::get_user()));
		} else {
			$exists = DB::GetOne('SELECT 1 FROM base_notify WHERE token=%s', array($token));
			if (!$exists) {
				DB::Execute('DELETE FROM base_notify WHERE single_cache_uid=%d AND telegram=0',array(Base_AclCommon::get_user()));
				DB::Execute('INSERT INTO base_notify (token, cache) VALUES (%s, %s)', array($token, self::serialize(array())));
			}
		}
		return $token;
	}
	
	public static function get_notifications($token,$tray=true) {
		$ret = array();

        $notification_modules = ModuleManager::check_common_methods('notification');
        
        foreach ($notification_modules as $module) {
        	$timeout = self::get_module_setting($module);
        	if ($timeout == -1) continue;

            $callback = array($module. "Common", 'notification');
            if (is_callable($callback)) {
                $notify = call_user_func($callback);
            } else {
                $notify = null;
            }

        	if (!isset($notify[$tray?'tray':'notifications'])) continue;
        	
        	$new_module_notifications = self::filter_new_notifications($module, $notify[$tray?'tray':'notifications'], $token);
        	
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
		return Base_User_SettingsCommon::get('Base_Notify', 'general_timeout');
	}

	public static function get_module_setting($module) {
		$module = rtrim($module);
		$module_setting = Base_User_SettingsCommon::get('Base_Notify', $module.'_timeout');
		return ($module_setting == -2) ? self::get_general_setting(): $module_setting;
	}	

	public static function user_settings() {
		$ret = array(
				array('name'=>null,'label'=>__('General'),'type'=>'header'),
				array('name'=>'one_cache','label'=>__('Show each notification'),'type'=>'select', 'values'=>array(0=>__('multiple times every login and on each device'), 1=>__('only once and only on one device')), 'default'=>1),
				array('name'=>null,'label'=>__('Browser Notification').' - '.__('General'),'type'=>'header'),
				array('name'=>'general_timeout', 'reload'=>1, 'label'=>__('Close Message Timeout'),'type'=>'select','values'=>Utils_CommonDataCommon::get_translated_array('Base_Notify/Timeout', 'position'),'default'=>0),
				array('name'=>'general_group','label'=>__('Group Similar Notifications'),'type'=>'checkbox','default'=>1),
				array('name'=>'browser_settings', 'label'=>'','type'=>'static','values'=>'<a class="button" onClick="Base_Notify.notify (\'Notification\', {body: \'enabled\', icon: \''.self::get_icon('Base_Notify').'\'}, true);">'.(__('Browser Settings')).'</a>'),

				array('name'=>null,'label'=>__('Browser Notification').' - '.__('Module Specific Timeout'),'type'=>'header')
		);
	
		$modules = ModuleManager::check_common_methods('notification');
	
		foreach ($modules as $module) {
			$label = self::get_module_caption($module);
	
			$ret = array_merge($ret, array(array('name'=>$module.'_timeout','label'=>$label,'type'=>'select','values'=>array(-2=>_M('Use general setting')) + Utils_CommonDataCommon::get_translated_array('Base_Notify/Timeout', 'position'),'default'=>-2)));
		}

		$ret[] = array('name'=>null,'label'=>__('Telegram Notification'),'type'=>'header');
		$telegram = DB::GetOne('SELECT 1 FROM base_notify WHERE single_cache_uid=%d AND telegram=1',array(Base_AclCommon::get_user()));
		if($telegram && isset($_GET['telegram'])) {
			$telegram = 0;
			DB::Execute('UPDATE base_notify SET telegram=0 WHERE single_cache_uid=%d',array(Base_AclCommon::get_user()));
		}
		$ret[] = array('name'=>'telegram_url', 'label'=>'<a class="button" href="modules/Base/Notify/telegram.php" target="_blank">'.($telegram?__('Connect to another telegram account'):__('Connect to your telegram account')).'</a>','type'=>'static','values'=>($telegram?'<a class="button" '.Module::create_href(array('telegram'=>1)).'>'.__('Disconnect telegram').'</a>':''));

		return array(__('Notifications')=>$ret);
	}

	public static function user_settings_icon() {
		return Base_ThemeCommon::get_template_file(self::module_name(),'icon.png');
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
		$uncompressed = function_exists('gzuncompress')? @gzuncompress($decoded): $decoded;
		return @unserialize($uncompressed);
	}

    public static function cron() {
        return array('telegram'=>(self::refresh_rate_telegram/60));
    }

    public static function telegram() {
        $tokens = DB::GetAssoc('SELECT token,single_cache_uid FROM base_notify WHERE telegram=1 AND single_cache_uid is not null');
		if(!$tokens) return;
        $ret = array();
		$map = array();
		$refresh_time = time();
		$notified_cache = array();
        foreach($tokens as $token=>$uid) {
			$msgs = array();
			if (Base_NotifyCommon::is_refresh_due_telegram($token)) {
				Base_AclCommon::set_user($uid);

				$notified_cache[$token] = array();
				$notifications = Base_NotifyCommon::get_notifications($token);

				foreach ($notifications as $module => $module_new_notifications) {
					foreach ($module_new_notifications as $id => $message) {
						$notified_cache[$token][$module][] = $id;

						$title = EPESI . ' ' . Base_NotifyCommon::strip_html($message['title']);
						$body = Base_NotifyCommon::strip_html($message['body']);
						//$icon = Base_NotifyCommon::get_icon($module, $message);

						$msgs[] = array('title' => $title, 'body' => $body);
					}
				}
			}
			$remote_token = md5($uid.'#'.Base_UserCommon::get_user_login($uid).'#'.$token);
            $ret[$remote_token] = $msgs?$msgs:'0';
			$map[$remote_token] = $token;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,"https://telegram.epesicrm.com/");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($ret));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $status = curl_exec ($ch);
        curl_close ($ch);
		$status = @json_decode($status);
		if(is_array($status)) {
			foreach($status as $remove) if(isset($map[$remove])) {
				DB::Execute('UPDATE base_notify SET telegram=0 WHERE token=%s',array($map[$remove]));
				unset($notified_cache[$map[$remove]]);
			}
			foreach($notified_cache as $token=>$nc) Base_NotifyCommon::set_notified_cache($nc, $token, $refresh_time);
		}
    }
}

on_init(array('Base_NotifyCommon', 'init'));

?>