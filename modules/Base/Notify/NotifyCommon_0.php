<?php
/**
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 1.0
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

		eval_js_once("if (notify.isSupported) {
		clearInterval(Base_Notify__interval);
		var Base_Notify__interval = setInterval(function () {Base_Notify__refresh('".CID."');}, ".(self::refresh_rate*1000).");
		}");

		eval_js_once('function Base_Notify__alert () {alert(\''.__('Notifications disabled or not supported!').'\n'.__('Check your browser settings and allow notifications to use this feature...').'\');}');
	}

	public static function strip_html ($text) {
		return str_replace('&nbsp;',' ',htmlspecialchars_decode(strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/',"\n",$text))));
	}

	public static function user_settings($settings_edit = false){
		if ($settings_edit)
		Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file('Base_Notify', 'icon.png'),__('Browser Settings'), 'onClick="Base_Notify__notify (\'Notification\', {body: \'enabled\', icon: \''.self::get_icon('Base_Notify').'\'}, true);"', __('Click to set browser settings for tray notifications'));

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

	public static function get_notifications() {
		return ModuleManager::call_common_methods('tray_notification', false, array(time()-self::reset_time*3600));
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

	public static function get_general_setting() {
		return Base_User_SettingsCommon::get('Base_Notify', 'general_timeout');
	}

	public static function group_similar() {
		return Base_User_SettingsCommon::get('Base_Notify', 'general_group')==1;
	}

	public static function get_module_setting($module) {
		$module = rtrim($module);
		$module_setting = Base_User_SettingsCommon::get('Base_Notify', $module.'_timeout');

		return ($module_setting == -2) ? self::get_general_setting(): $module_setting;
	}

	public static function get_icon($module, $message = null) {
		$icon = Base_ThemeCommon::get_template_file($module, isset($message['icon']) ? $message['icon']:'icon.png');
		return isset($icon)? $icon: Base_ThemeCommon::get_template_file('Base_Notify', 'icon.png');
	}
	
	public static function get_new_messages($module, $all_messages) {
		if (!isset($_SESSION['Base_Notify']['notified_cache'][$module])) return $all_messages;
		
		return array_diff_key($all_messages, $_SESSION['Base_Notify']['notified_cache'][$module]);
	}	
}

on_init(array('Base_NotifyCommon', 'init'));

?>