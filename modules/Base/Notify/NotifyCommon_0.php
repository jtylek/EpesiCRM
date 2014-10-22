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
	public static function strip_html ($text) {
		return str_replace('&nbsp;',' ',htmlspecialchars_decode(strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/',"\n",$text))));
	}

	public static function user_settings(){
		$caller = debug_backtrace();

		if (isset($caller[2]['function']) && $caller[2]['function']=='get_default')
		Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file('Base_Notify', 'icon.png'),__('Browser Settings'), 'onClick="Base_Notify__notify (\'Notification\', {body: \'enabled\', icon: \''.self::get_icon('Base_Notify').'\'}, true);"', __('Click to set browser settings for tray notifications'));
			
		$ret = array(
		array('name'=>null,'label'=>__('General'),'type'=>'header'),
		array('name'=>'general_timeout','label'=>__('Close Message Timeout'),'type'=>'select','values'=>Utils_CommonDataCommon::get_translated_array('Base_TrayNotify/Timeout', true),'default'=>0),
		array('name'=>'general_group','label'=>__('Group Similar Notifications'),'type'=>'checkbox','default'=>1),

		array('name'=>null,'label'=>__('Module Specific Timeout'),'type'=>'header')
		);

		$modules = array_keys(self::get_notifications());

		foreach ($modules as $module) {
			$label = self::get_module_caption($module);

			$ret = array_merge($ret, array(array('name'=>$module.'_timeout','label'=>$label,'type'=>'select','values'=>array(-2=>_M('Use general setting')) + Utils_CommonDataCommon::get_translated_array('Base_TrayNotify/Timeout', true),'default'=>-2)));
		}

		return array(__('Notify')=>$ret);
	}
	
	public static function get_notifications() {
		return ModuleManager::call_common_methods('tray_notification',false, array(time()-Base_Notify::reset_time*3600));
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
}

?>