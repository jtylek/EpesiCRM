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

	public function body() {
		
	}
	
	public function applet($conf, $opts) {
		$records = DB::GetAssoc('SELECT internal_id,category_id FROM utils_watchdog_subscription AS uws WHERE user_id=%d AND last_seen_event<(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id)', array(Acl::get_user()));
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		foreach ($methods as $k=>$v) 
			$methods[$k] = explode('::',$v);
		$gb = $this->init_module('Utils/GenericBrowser','subscriptions','subscriptions');
		$gb->set_table_columns(array(
//								array('name'=>'Sub','width'=>1),
								array('name'=>'Cat.','width'=>1),
								array('name'=>'Title')//LANG!
								));
		foreach ($records as $k=>$v) {
			$gb_row = $gb->get_new_row();
			$data = call_user_func($methods[$v], $k);
			$gb_row->add_data(
//				Utils_WatchdogCommon::get_change_subscription_icon($v,$k), 
				$data['category'], 
				$data['title']
			);
			$gb_row->add_action($data['view_href'],'View');
			$gb_row->add_info(Utils_WatchdogCommon::implode_events(Utils_WatchdogCommon::check_if_notified($v, $k)));
		}
		$this->display_module($gb);
	}

}

?>