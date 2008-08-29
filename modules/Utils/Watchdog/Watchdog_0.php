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

class Utils_Watchdog extends Module {
	private $lang;

	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	public function body() {
		
	}
	
	public function purge_subscriptions_applet() {
		DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id) WHERE user_id=%d', array(Acl::get_user()));
		location(array());
		return false;
	}
	
	public function notified($id) {
		DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id) WHERE user_id=%d AND internal_id=%d', array(Acl::get_user(), $id));
		location(array());
		return false;
	}
	
	public function applet($conf, $opts) {
		$records = DB::GetAssoc('SELECT internal_id,category_id FROM utils_watchdog_subscription AS uws WHERE user_id=%d AND last_seen_event<(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id)', array(Acl::get_user()));
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		foreach ($methods as $k=>$v) 
			$methods[$k] = explode('::',$v);
		$gb = $this->init_module('Utils/GenericBrowser','subscriptions','subscriptions');
		$gb->set_table_columns(array(
//								array('name'=>'Sub','width'=>1),
								array('name'=>$this->lang->t('Cat.'),'width'=>1),
								array('name'=>$this->lang->t('Title'))
								));
		if (!empty($records)) $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('Mark all entries as read')).' '.$this->create_confirm_callback_href($this->lang->t('This will update all of your subscriptions status in selected categories, are you sure you want to continue?'),array($this,'purge_subscriptions_applet')).'><img src="'.Base_ThemeCommon::get_template_file('Utils_Watchdog','purge.png').'" border="0"></a>';
		foreach ($records as $k=>$v) {
			$changes = Utils_WatchdogCommon::check_if_notified($v, $k);
			if (!is_array($changes)) $changes = array();
			$data = call_user_func($methods[$v], $k, $changes);
			if ($data==null) continue;
			$gb_row = $gb->get_new_row();
			$gb_row->add_data(
//				Utils_WatchdogCommon::get_change_subscription_icon($v,$k), 
				$data['category'], 
				$data['title']
			);
			$gb_row->add_action($data['view_href'],'View');
			$gb_row->add_action($this->create_callback_href(array($this,'notified'), array($k)),'Restore','Mark as read');
			if (isset($data['events']) && $data['events']) $gb_row->add_info($data['events']);
		}
		$this->display_module($gb);
	}

}

?>