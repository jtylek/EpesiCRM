<?php
/**
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage Watchdog
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('modules/Base/Theme/bootstrap_icons.php');

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
	
	public function applet($conf, & $opts) {
		$categories = array();
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		foreach ($methods as $k=>$v) { 
			$methods[$k] = explode('::',$v);
			if (isset($conf['category_'.$k]) && $conf['category_'.$k] && is_numeric($k)) $categories[] = $k;
		}
		if (empty($categories)) {
			print(__('No category selected'));
			return;
		}
        $records_limit = $conf['records_limit'] ?? 15;
        if ($records_limit == '__all__') {
            $records_limit = null;
        }
		$header = array(
					// Utils_GenericBrowser normalizes widths against their sum, so
					// these are effectively percentages (15/85). The category is a
					// single short recordset caption; Title is what actually
					// truncates, so it gets the rest.
					array('name'=>__('Category'),'width'=>15),
					array('name'=>__('Title'),'width'=>85)
					);
		if (count($categories)==1) {
			$title = call_user_func($methods[$categories[0]]);
			$opts['title'] = __('Watchdog - %s', array($title['category']));
			$header = array(array('name'=>__('Title')));
		} elseif (count($categories)==count($methods)) {
			$opts['title'] = __('Watchdog - All');
		} else {
			$opts['title'] = __('Watchdog - Selection');
		}
		if($conf['title']) {
			$opts['title'] = __('Watchdog - %s',array($conf['title']));
		}
		$records = Utils_WatchdogCommon::get_records_with_new_notifications();
		$gb = $this->init_module(Utils_GenericBrowser::module_name(),'subscriptions','subscriptions');
		$gb->set_table_columns($header);
		$something_to_purge = false;
		$count = 0;
        load_js('modules/Utils/Watchdog/applet_mark_as_read.js');
		foreach ($records as $rec_key => $w) {
			$k = $w['internal_id'];
			$v = $w['category_id'];
			$changes = Utils_WatchdogCommon::check_if_notified($v, $k);
			if (!is_array($changes)) $changes = array();
			$data = call_user_func($methods[$v], $k, $changes);
			if ($data == null) { // mark events as seen when user can't see them
                Utils_WatchdogCommon::notified($v, $k);
                unset($records[$rec_key]);
                continue;
            }
			$gb_row = $gb->get_new_row();
			// A type glyph in front of the title, since the Category column is dropped
			// entirely when a single category is selected and is a truncated word
			// even when it isn't. A category is registered as
			// "<Module>Common::watchdog_label" (Utils_WatchdogCommon::
			// register_category()), so the owning module is the callback class minus
			// its "Common" suffix - which is where the icon is declared. The
			// recordset name (set by Utils_RecordBrowserCommon::watchdog_label(), the
			// only frame that knows it) lets a module owning more than one category
			// give each its own icon rather than having them all read alike - that's
			// CRM_Contacts, whose 'company' rows would otherwise carry Contacts'
			// person glyph. Absent for a category that isn't RecordBrowser-backed,
			// which just falls back to the module's own icon.
			$bi_icon = Base_BootstrapIcons::type_tag(preg_replace('/Common$/', '', $methods[$v][0]), $data['recordset'] ?? null);
			if (count($categories)==1) {
				$gb_row->add_data(
					$bi_icon.$data['title']
				);
			} else {  
				$gb_row->add_data(
					$data['category'], 
					$bi_icon.$data['title']
				);
			}
			$gb_row->add_action(Utils_WatchdogCommon::get_confirm_change_subscr_href($v, $k),'Stop Watching',__('Click to stop watching this record for changes'), Base_ThemeCommon::get_template_file(Utils_Watchdog::module_name(),'watching_small_new_events.png'));
			$gb_row->add_action($data['view_href'],'View');
            $gb_row->set_attrs('name="watchdog_table_row_'.$v.'__'.$k.'"');
            $gb_row->add_action('href="javascript:void(0);" onclick="watchdog_applet_mark_as_read(\''.$v.'__'.$k.'\')"','Mark as Read',__('Mark as read'),Base_ThemeCommon::get_template_file(Utils_Watchdog::module_name(),'mark_as_read.png'));
            $something_to_purge = true;
			if (isset($data['events']) && $data['events']) $gb_row->add_info($data['events'], true, null, true);
			$count++;
			if ($records_limit && $count >= $records_limit) break;
		}
		$records_qty = count($records);
		if ($records_limit && $count < $records_qty)
			print(__('Displaying %s of %s records', array($count, $records_qty)));
		$this->set_module_variable('display_at_time', time());
		if ($something_to_purge) $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('Mark all entries as read')).' '.$this->create_confirm_callback_href(__('This will mark all entries in selected categories as read, are you sure you want to continue?'),$this->purge_subscriptions_applet(...), array($categories)).'><i class="bi bi-book-fill"></i></a>';
		// Wrapper class, not the table itself: theme_adminltedark's GenericBrowser CSS
		// ("Utils_Watchdog's own dashboard applet", Utils/GenericBrowser/theme_adminltedark/
		// default.css) uses it to opt this applet's row actions out of the mobile kebab collapse
		// - same mechanism/reasoning as Base_Admin's own "epesi-admin-panel" wrapper
		// (Admin_0.php::body()), just for this applet instead of the whole admin panel. This is a
		// small dashboard tile, easily narrower than the collapse's 991.98px breakpoint even in
		// an otherwise-wide browser window, and its own action set (Stop Watching/View/Mark as
		// Read) is short enough to always fit without needing the extra tap.
		print('<div class="epesi-watchdog-applet">');
		$this->display_module($gb);
		print('</div>');
	}

}

?>