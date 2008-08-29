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
	
	public function purge_subscriptions_applet($cat_ids) {
		foreach ($cat_ids as $cat_id) {
			Utils_WatchdogCommon::purge_notifications($cat_id);
		}
		location(array());
		return false;
	}
	
	public function notified($id, $cat_id) {
		Utils_WatchdogCommon::notified($cat_id,$id);
		location(array());
		return false;
	}
	
	public function applet($conf, $opts) {
		$categories = array();
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		foreach ($methods as $k=>$v) { 
			$methods[$k] = explode('::',$v);
			if (isset($conf['category_'.$k]) && $conf['category_'.$k] && is_numeric($k)) $categories[] = $k;
		}
		if (empty($categories)) {
			print($this->lang->t('No category selected'));
			return;
		}
		$header = array(
					array('name'=>$this->lang->t('Cat.'),'width'=>1),
					array('name'=>$this->lang->t('Title'))
					);
		if (count($categories)==1) {
			$title = call_user_func($methods[$categories[0]]);
			$opts['title'] = 'Subscriptions - '.$title['category'];
			$header = array(array('name'=>$this->lang->t('Title')));
		} elseif (count($categories)==count($methods)) {
			$opts['title'] = 'Subscriptions - All';
		} else {
			$opts['title'] = 'Subscriptions - Selection';
		}
		if (isset($conf['only_new']) && $conf['only_new']) $only_new = ' AND last_seen_event<(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id)';
		else $only_new = '';
		$records = DB::GetAssoc('SELECT internal_id,category_id FROM utils_watchdog_subscription AS uws WHERE user_id=%d '.$only_new.'AND category_id IN ('.implode(',',$categories).')', array(Acl::get_user()));
		$gb = $this->init_module('Utils/GenericBrowser','subscriptions','subscriptions');
		$gb->set_table_columns($header);
		$something_to_purge = false;
		foreach ($records as $k=>$v) {
			$changes = Utils_WatchdogCommon::check_if_notified($v, $k);
			if (!is_array($changes)) $changes = array();
			$data = call_user_func($methods[$v], $k, $changes);
			if ($data==null) continue;
			$gb_row = $gb->get_new_row();
//			Utils_WatchdogCommon::get_change_subscription_icon($v,$k),
			if (count($categories)==1) {
				$gb_row->add_data(
					$data['title']
				);
			} else {  
				$gb_row->add_data(
					$data['category'], 
					$data['title']
				);
			}
			$gb_row->add_action($data['view_href'],'View');
			if ($only_new || Utils_WatchdogCommon::check_if_notified($v, $k)!==true) {
				$gb_row->add_action($this->create_callback_href(array($this,'notified'), array($k, $v)),'Restore','Mark as read');
				$something_to_purge = true;
			}
			if (isset($data['events']) && $data['events']) $gb_row->add_info($data['events']);
		}
		if ($something_to_purge) $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('Mark all entries as read')).' '.$this->create_confirm_callback_href($this->lang->t('This will update all of your subscriptions status in selected categories, are you sure you want to continue?'),array($this,'purge_subscriptions_applet'), array($categories)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_Watchdog','purge.png').'" border="0"></a>';
		$this->display_module($gb);
	}

}

?>