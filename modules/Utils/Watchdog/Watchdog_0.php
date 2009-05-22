<?php
/**
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage Watchdog
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Watchdog extends Module {
	public function body() {
		
	}
	
	public function purge_subscriptions_applet($cat_ids) {
		foreach ($cat_ids as $cat_id) {
			Utils_WatchdogCommon::purge_notifications($cat_id, $this->get_module_variable('display_at_time'));
		}
		location(array());
		return false;
	}
	
	public function notified($cat_id, $id) {
		Utils_WatchdogCommon::notified($cat_id, $id);
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
			print($this->t('No category selected'));
			return;
		}
		$header = array(
					array('name'=>$this->t('Cat.'),'width'=>1),
					array('name'=>$this->t('Title'))
					);
		if (count($categories)==1) {
			$title = call_user_func($methods[$categories[0]]);
			$opts['title'] = Base_LangCommon::ts('Premium/Projects/Tickets','Subscriptions - ').$title['category'];
			$header = array(array('name'=>$this->t('Title')));
		} elseif (count($categories)==count($methods)) {
			$opts['title'] = Base_LangCommon::ts('Premium/Projects/Tickets','Subscriptions - All');
		} else {
			$opts['title'] = Base_LangCommon::ts('Premium/Projects/Tickets','Subscriptions - Selection');
		}
		if (isset($conf['only_new']) && $conf['only_new']) $only_new = ' AND last_seen_event<(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id)';
		else $only_new = '';
		$records = DB::GetAll('SELECT internal_id,category_id FROM utils_watchdog_subscription AS uws WHERE user_id=%d '.$only_new.'AND category_id IN ('.implode(',',$categories).')', array(Acl::get_user()));
		$gb = $this->init_module('Utils/GenericBrowser','subscriptions','subscriptions');
		$gb->set_table_columns($header);
		$something_to_purge = false;
		$count = 0;
		foreach ($records as $w) {
			$k = $w['internal_id'];
			$v = $w['category_id'];
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
			$gb_row->add_action(Utils_WatchdogCommon::get_confirm_change_subscr_href($v, $k),'<img src="'.Base_ThemeCommon::get_template_file('Utils/Watchdog','unsubscribe_small_new_events.png').'" border="0" />','Click to unsubscribe');
			$gb_row->add_action($data['view_href'],'View');
			if ($only_new || Utils_WatchdogCommon::check_if_notified($v, $k)!==true) {
//				$gb_row->add_action($this->create_callback_href(array($this,'notified'), array($v, $k)),'Restore','Mark as read');
				$gb_row->set_attrs('name="watchdog_table_row_'.$v.'__'.$k.'"');
				load_js('modules/Utils/Watchdog/applet_mark_as_read.js');
				$gb_row->add_action('href="javascript:void(0);" onclick="watchdog_applet_mark_as_read(\''.$v.'__'.$k.'\')"','Restore','Mark as read');
				$something_to_purge = true;
			}
			if (isset($data['events']) && $data['events']) $gb_row->add_info($data['events']);
			$count++;
			if ($count==15) break;
		}
		$records_qty = count($records);
		if ($records_qty>15 && $count==15)
			print($this->t('Displaying %s of %s records', array($count, $records_qty)));
		$this->set_module_variable('display_at_time', time());
		if ($something_to_purge) $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs($this->t('Mark all entries as read')).' '.$this->create_confirm_callback_href($this->t('This will update all of your subscriptions status in selected categories, are you sure you want to continue?'),array($this,'purge_subscriptions_applet'), array($categories)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_Watchdog','purge.png').'" border="0"></a>';
		$this->display_module($gb);
	}

}

?>