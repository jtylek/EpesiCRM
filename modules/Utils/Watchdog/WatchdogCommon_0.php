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

class Utils_WatchdogCommon extends ModuleCommon {
	private static $log = false;

	public static function menu() {
		return array('Watchdog'=>array('__icon__'=>'bell'));
	}

	public static function user_settings() {
		return array(
			__('Notifications')=>array(
				array('name'=>'header_email','label'=>__('Watchdog notifications'),'type'=>'header'),
				array('name'=>'email', 'label'=>__('Send e-mail on new events'), 'type'=>'checkbox', 'default'=>false)
			)
		);
	}

	public static function applet_caption() {
		return __('Watchdog');
	}
	public static function applet_info() {
		return __('Helps tracking changes made in the system');
	}
	public static function applet_settings() {
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		$ret = array();
		$ret[] = array('label'=>__('Title'),'name'=>'title','type'=>'text','default' => '');
        $ret[] = array('label'=>__('Records limit'),'name'=>'records_limit','type'=>'select', 'values' => array(/*'__all__' => __('All'), */'10' => '10', '15' => '15', '20' => '20', '30' => '30'), 'default' => '15');

		if (!empty($methods)) {
			$ret[] = array('label'=>__('Categories'),'name'=>'categories_header','type'=>'header');
			foreach ($methods as $k=>$v) {
				$method = explode('::',$v);
				IF (!is_callable($method)) continue;
				$methods[$k] = call_user_func($method);
				$ret[] = array('label'=>$methods[$k]['category'],'name'=>'category_'.$k,'type'=>'checkbox','default'=>true);
			}
		}
		return $ret;
	}

    public static function cron()
    {
        return array('cron_send_notifications' => 1, 'cron_cleanup' => 7*24*60);
    }

    public static function cron_send_notifications()
    {
        while(false != ($event_id = self::pop_queued_notification_for_cron())) {
            self::send_email_notifications($event_id);
        }
    }

	public static function cron_cleanup()
	{
		Utils_Watchdog_Cleaner::instance()->cron();
	}

	public static function get_subscribers($category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if ($id!==null) $ret = DB::GetAssoc('SELECT user_id,user_id FROM utils_watchdog_subscription WHERE category_id=%d AND internal_id=%s', array($category_id, $id));
		else $ret = DB::GetAssoc('SELECT user_id,user_id FROM utils_watchdog_category_subscription WHERE category_id=%d', array($category_id));
		return $ret;
	}
	public static function get_category_id($category_name, $report_error=true) {
		static $cache = array();
		if (isset($cache[$category_name])) return $cache[$category_name];
		if (is_numeric($category_name)) return $category_name;
		$ret = DB::GetOne('SELECT id FROM utils_watchdog_category WHERE name=%s', array(md5($category_name)));
		if ($ret===false || $ret===null) {
//			if ($report_error) trigger_error('Invalid category given: '.$category_name.', category not found.');
			return null;
		}
		return $cache[$category_name] = $ret;
	}
	public static function category_exists($category_name) {
		static $cache = null;
		if (!$cache) {
			$cache = DB::GetAssoc('SELECT name, id FROM utils_watchdog_category');
		}
		return isset($cache[md5($category_name)]);
	}
	private static function check_if_user_subscribes($user, $category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if ($id!==null) $last_seen = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user,$id,$category_id));
		else $last_seen = DB::GetOne('SELECT 1 FROM utils_watchdog_category_subscription WHERE user_id=%d AND category_id=%d',array($user,$category_id));
		return ($last_seen!==false && $last_seen!==null);
	}
	// ****************** registering ************************
	public static function register_category($category_name, $callback) {
		$exists = DB::GetOne('SELECT name FROM utils_watchdog_category WHERE name=%s',array(md5($category_name)));
		if ($exists!==false && $exists!==null) return;
		if (is_array($callback)) $callback = implode('::',$callback);
		DB::Execute('INSERT INTO utils_watchdog_category (name, callback) VALUES (%s,%s)',array(md5($category_name),$callback));
	}

	public static function unregister_category($category_name) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		DB::Execute('DELETE FROM utils_watchdog_category_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_subscription WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_event WHERE category_id=%d',array($category_id));
		DB::Execute('DELETE FROM utils_watchdog_category WHERE id=%d',array($category_id));
	}
	// *********************************** New event ***************************
	private static $disabled=false;
	public static function dont_notify($d=true) {
		self::$disabled=$d;
	}

    public static function email_mode($set = null)
    {
        static $email_mode = false;
        if ($set !== null) {
            $email_mode = ($set == true);
        }
        return $email_mode;
    }
	public static function new_event($category_name, $id, $message) {
		if(self::$disabled) return;
		$category_id = self::get_category_id($category_name, false);
		if (!$category_id) return;
		DB::Execute('INSERT INTO utils_watchdog_event (category_id, internal_id, message, event_time) VALUES (%d,%d,%s,%T)',array($category_id,$id,$message,time()));
		$event_id = DB::Insert_ID('utils_watchdog_event', 'id');
		$count = DB::GetOne('SELECT COUNT(*) FROM utils_watchdog_event WHERE category_id=%d AND internal_id=%d', array($category_id,$id));
		if ($count==1) {
			$subscribers = self::get_subscribers($category_id);
			foreach ($subscribers as $s)
				self::user_subscribe($s, $category_name, $id);
		}
        Utils_WatchdogCommon::notified($category_name,$id);
		$subscribers = self::get_subscribers($category_name, $id);
		foreach ($subscribers as $user) {
			if (!self::has_access_to_record($user, $category_name, $id)) {
				self::user_notified($user, $category_name, $id);
			}
		}

        self::queue_notification_for_cron($event_id);
    }

    public static function send_email_notifications($event_id)
    {
        $event = DB::GetRow('SELECT * FROM utils_watchdog_event WHERE id=%d', array($event_id));
        if (!$event) return;

        $category_id = $event['category_id'];
        $id = $event['internal_id'];
        $message = $event['message'];
        $subscribers = self::get_subscribers($category_id, $id);

        $c_user = Acl::get_user();
        self::email_mode(true);
        foreach ($subscribers as $user_id) {
            $wants_email = Base_User_SettingsCommon::get('Utils_Watchdog', 'email', $user_id);
            if (!$wants_email) continue;
            Acl::set_user($user_id);
            Base_LangCommon::load();
            $email_data = self::display_events($category_id, array($event_id => $message), $id, true);
            if (!$email_data) continue;
            $contact = Utils_RecordBrowserCommon::get_id('contact', 'login', $user_id);
            if (!$contact) continue;
            $email = Utils_RecordBrowserCommon::get_value('contact', $contact, 'email');
            if (!$email) continue;
            $title = __('%s notification - %s - %s', array(EPESI, $email_data['category'], strip_tags($email_data['title'])));
            Base_MailCommon::send($email, $title, $email_data['events'], null, null, true);
        }
        Acl::set_user($c_user);
        Base_LangCommon::load();
        self::email_mode(false);
    }

    public static function queue_notification_for_cron($event_id)
    {
        DB::Execute('INSERT INTO utils_watchdog_notification_queue VALUES (%d)', array($event_id));
    }

    public static function pop_queued_notification_for_cron()
    {
        DB::StartTrans();
        $event_id = DB::GetOne('SELECT event_id FROM utils_watchdog_notification_queue');
        DB::Execute('DELETE FROM utils_watchdog_notification_queue WHERE event_id=%d', array($event_id));
        DB::CompleteTrans();
        return $event_id;
    }
	// *************************** Subscription manipulation *******************
	public static function user_purge_notifications($user_id, $category_name, $time=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if ($time===null) $time=time();
		DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id AND (event_time<=%T OR event_time IS NULL)) WHERE user_id=%d AND category_id=%d', array($time, $user_id, $category_id));
		DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=-1 WHERE last_seen_event IS NULL');
	}
	public static function user_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===null || $last_event===false) $last_event = -1;
		DB::Execute('UPDATE utils_watchdog_subscription SET last_seen_event=%d WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($last_event,$user_id,$id,$category_id));
		$min_last_seen = DB::GetOne('SELECT MIN(last_seen_event) FROM utils_watchdog_subscription WHERE internal_id=%d AND category_id=%d',array($id,$category_id));
		DB::Execute('DELETE FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d AND (id<%d OR event_time<=%T)', array($id,$category_id,$min_last_seen, date('Y-m-d H:i:s', strtotime('-3 month'))));
	}

	public static function user_subscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$lse = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d AND id<(SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d)', array($id, $category_id, $id, $category_id));
		if ($lse===false || $lse===null) $lse=-1;
		$already_subscribed = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($already_subscribed===false || $already_subscribed===null) DB::Execute('INSERT INTO utils_watchdog_subscription (last_seen_event, user_id, internal_id, category_id) VALUES (%d,%d,%d,%d)',array($lse,$user_id,$id,$category_id));
		if ($user_id==Acl::get_user()) self::notified($category_name, $id);
		if (self::$log) error_log('User '.$user_id.' subscribed to '.$category_name.':'.$id."\n",3,'data/subscriptions.log');
	}

	public static function user_change_subscription($user_id, $category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$already_subscribed = self::check_if_user_subscribes($user_id,$category_id,$id);
		if ($id===null) {
			if ($already_subscribed!==false && $already_subscribed!==null) DB::Execute('DELETE FROM utils_watchdog_category_subscription WHERE user_id=%d AND category_id=%d',array($user_id,$category_id));
			else DB::Execute('INSERT INTO utils_watchdog_category_subscription (user_id, category_id) VALUES (%d,%d)',array($user_id,$category_id));
		} else {
			if ($already_subscribed!==false && $already_subscribed!==null) DB::Execute('DELETE FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
			else {
				DB::Execute('INSERT INTO utils_watchdog_subscription (last_seen_event, user_id, internal_id, category_id) VALUES (%d,%d,%d,%d)',array(-1,$user_id,$id,$category_id));
				if ($user_id==Acl::get_user()) self::notified($category_name, $id);
			}
		}
		if (self::$log) error_log('User '.$user_id.' '.($already_subscribed?'un-':'').'watched '.$category_name.':'.$id."\n",3,'data/subscriptions.log');
	}

	public static function user_unsubscribe($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if ($user_id!==null) DB::Execute('DELETE FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		else DB::Execute('DELETE FROM utils_watchdog_subscription WHERE internal_id=%d AND category_id=%d',array($id,$category_id));
		if (self::$log) error_log('User '.$user_id.' unsubscribed to '.$category_name.':'.$id."\n",3,'data/subscriptions.log');
	}

	public static function user_check_if_notified($user_id, $category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_seen = DB::GetOne('SELECT last_seen_event FROM utils_watchdog_subscription WHERE user_id=%d AND internal_id=%d AND category_id=%d',array($user_id,$id,$category_id));
		if ($last_seen===false || $last_seen===null) return null;
		$last_event = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d', array($id,$category_id));
		if ($last_event===false || $last_event===null) $last_event=-1;
		if ($last_seen==$last_event || $last_event==-1) return true;
		$ret = array();

		$missed_events = DB::Execute('SELECT id,message FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d AND id>%d ORDER BY id ASC', array($id,$category_id,$last_seen));
		while ($row = $missed_events->FetchRow())
			$ret[$row['id']] = $row['message'];
		return $ret;
	}

	public static function user_get_confirm_change_subscr_href($user, $category_name, $id=null) {
		return Module::create_confirm_href(__('Are you sure you want to stop watching this record?'),self::user_get_change_subscr_href_array($user, $category_name, $id));
	}
	public static function user_get_change_subscr_href($user, $category_name, $id=null) {
		return Module::create_href(self::user_get_change_subscr_href_array($user, $category_name, $id));
	}
	public static function user_get_change_subscr_href_array($user, $category_name, $id=null) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		if (isset($_REQUEST['utils_watchdog_category']) &&
			isset($_REQUEST['utils_watchdog_user']) &&
			$_REQUEST['utils_watchdog_category']==$category_id &&
			$_REQUEST['utils_watchdog_user']==$user &&
			((isset($_REQUEST['utils_watchdog_id']) &&
			$_REQUEST['utils_watchdog_id']==$id) ||
			(!isset($_REQUEST['utils_watchdog_id']) &&
			$id===null))) {
			self::user_change_subscription($user, $category_name, $id);
			unset($_REQUEST['utils_watchdog_category']);
			unset($_REQUEST['utils_watchdog_user']);
			unset($_REQUEST['utils_watchdog_id']);
			location(array());
		}
		return array('utils_watchdog_category'=>$category_id, 'utils_watchdog_user'=>$user, 'utils_watchdog_id'=>$id);
	}
	// **************** Subscription manipulation for logged user *******************
	public static function purge_notifications($category_name, $time=null) {
		self::user_purge_notifications(Acl::get_user(), $category_name, $time);
	}
	public static function notified($category_name, $id) {
		self::user_notified(Acl::get_user(), $category_name, $id);
	}
	public static function subscribe($category_name, $id) {
		self::user_subscribe(Acl::get_user(), $category_name, $id);
	}
	public static function unsubscribe($category_name, $id=null) {
		self::user_unsubscribe(Acl::get_user(), $category_name, $id);
	}
	public static function check_if_notified($category_name, $id) {
		return self::user_check_if_notified(Acl::get_user(), $category_name, $id);
	}
	public static function get_change_subscr_href($category_name, $id=null) {
		return self::user_get_change_subscr_href(Acl::get_user(), $category_name, $id);
	}
	public static function get_confirm_change_subscr_href($category_name, $id=null) {
		return self::user_get_confirm_change_subscr_href(Acl::get_user(), $category_name, $id);
	}
	public static function add_actionbar_change_subscription_button($category_name, $id=null) {
		if (!Base_AclCommon::check_permission('Watchdog - subscribe to categories')) return;
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$href = self::get_change_subscr_href($category_name, $id);
		$subscribed = self::check_if_user_subscribes(Acl::get_user(), $category_id, $id);
		if ($subscribed) {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','unwatch_big.png');
			$label = __('Stop Watching');
		} else {
			$icon = Base_ThemeCommon::get_template_file('Utils_Watchdog','watch_big.png');
			$label = __('Watch');
		}
		Base_ActionBarCommon::add($icon,$label,$href);
	}
	public static function display_events($category_name, $changes, $id, $for_email = false) {
		if (!is_array($changes)) return '';
		$category_id = self::get_category_id($category_name);
		$method = DB::GetOne('SELECT callback FROM utils_watchdog_category WHERE id=%d', array($category_id));
		$method = explode('::', $method);
		$data = call_user_func($method, $id, $changes, true, $for_email);
		if (!isset($data['events'])) return '';
		return $data;
	}
	public static function get_change_subscription_icon($category_name, $id) {
		$tag_id = 'watchdog_sub_button_'.$category_name.'_'.$id;
		return '<span id="'.$tag_id.'">'.self::get_change_subscription_icon_tags($category_name, $id).'</span>';
	}
	public static function get_change_subscription_icon_tags($category_name, $id) {
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_seen = self::check_if_notified($category_name, $id);
		load_js('modules/Utils/Watchdog/subscribe.js');
		$tag_id = 'watchdog_sub_button_'.$category_name.'_'.$id;
		$href = ' onclick="utils_watchdog_set_subscribe('.(($last_seen===null)?1:0).',\''.$category_name.'\','.$id.',\''.$tag_id.'\')" href="javascript:void(0);"';
		if ($last_seen===null) {
			$icon = 'eye-slash';
			$color = 'text-danger';
		} else {
			if ($last_seen===true) {
				$icon = 'eye';
				$color = 'text-success';
			} else {
				$icon = 'eye-slash';
				$color = 'text-danger';
			}
		}
		$tooltip = Utils_TooltipCommon::ajax_open_tag_attrs(array(__CLASS__, 'ajax_subscription_tooltip'), array($category_name, $id));
		return '<a '.$href.' '.$tooltip.'><i class="fa fa-'.$icon.' '.$color.'"></i></a>';
	}

	public static function ajax_subscription_tooltip($category_name, $id)
	{
		$category_id = self::get_category_id($category_name);
		if (!$category_id) return;
		$last_seen = self::check_if_notified($category_name, $id);
		if ($last_seen===null) {
			$tooltip = __('Click to watch this record for changes.');
		} else {
			if ($last_seen===true) {
				$tooltip = __('You are watching this record, click to stop watching this record for changes.');
			} else {
				$ev = self::display_events($category_id, $last_seen, $id);
				$tooltip = __('You are watching this record, click to stop watching this record for changes.').($ev?'<br>'.__('The following changes were made since the last time you were viewing this record:').'<br><br>'.$ev['events']:'');
			}
		}
		$subscribers = self::get_subscribers($category_name,$id);
		$my_user = Base_AclCommon::get_user();
		if ($subscribers) {
			$icon_on = 'eye';
			$icon_off = 'eye-slash';
			$other_subscribers = array();
			foreach ($subscribers as $subscriber) {
				if ($subscriber == $my_user) {
					continue;
				}
				if (!self::has_access_to_record($subscriber, $category_name, $id)) continue;
				if (class_exists('CRM_ContactsCommon')) {
					$contact = CRM_ContactsCommon::get_user_label($subscriber, true);
				} else {
					$contact = Base_UserCommon::get_user_login($subscriber);
				}

				$notified = self::user_check_if_notified($subscriber, $category_name, $id);
				$icon2 = $notified === true ? $icon_on : $icon_off;
				$other_subscribers[] = '<i class="fa fa-lw fa-lg fa-'.$icon2.'"></i><a>' . Utils_RecordBrowserCommon::no_wrap($contact) . '</a>';
			}
			if ($other_subscribers) {
				$tooltip .= '<hr />' . implode('<br>', $other_subscribers);
			}
		}
		return $tooltip;
	}

	public static function notification() {
		/*$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		foreach ($methods as $k=>$v) {
			$methods[$k] = explode('::',$v);
		}
        $time_sql = $time ? ' AND uwe.event_time > %T' : '';
		$only_new = " AND last_seen_event<(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id$time_sql)";
        $args = array(Acl::get_user());
        if ($time) {
            $args[] = $time;
        }
        $records = DB::GetAll('SELECT internal_id,category_id,last_seen_event FROM utils_watchdog_subscription AS uws WHERE user_id=%d '.$only_new, $args);
		$ret = array();
		$tray = array();
        if ($records) {
            $last_event_id = DB::GetOne('SELECT MAX(id) FROM utils_watchdog_event');
            foreach ($records as $v) {
                $changes = Utils_WatchdogCommon::check_if_notified($v['category_id'], $v['internal_id']);
                if (!is_array($changes)) $changes = array();
                $data = call_user_func($methods[$v['category_id']], $v['internal_id'], $changes, false);
                if ($data==null) continue;

                $msg = __("You've got unread notifications");
                $ret['watchdog_'. $last_event_id] = '<b>'.__('Watchdog - %s', array($msg)).'</b> ';
                $tray['watchdog_' . $last_event_id] = array('title'=>__('Watchdog'), 'body'=>$msg);
                break;
            }
        }*/

		$ret = array();
		$tray = array();
		$methods = DB::GetAssoc('SELECT id,callback FROM utils_watchdog_category');
		$records = self::get_records_with_new_notifications();
		foreach ($records as $rec_key => $w) {
			echo "$rec_key<br>";
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
			$ret['watchdog_'.$v.'_'.$k] = '<b>'.__('Watchdog - %s: %s', array($data['category'],$data['title'])).'</b>'.(isset($data['events'])?'<br />'.$data['events']:'');
			$tray['watchdog_'.$v.'_'.$k] = array('title'=>__('Watchdog - %s', array($data['category'])), 'body'=>$data['title']);
		}
		return array('notifications'=>$ret, 'tray'=>$tray);
	}

	public static function has_access_to_record($user_id, $tab, $id)
	{
		if (!Utils_RecordBrowserCommon::check_table_name($tab, false, false)) {
			return true;
		}
		$access = false;
        $old_user = Base_AclCommon::get_user();
        Base_AclCommon::set_user($user_id);
		$record = Utils_RecordBrowserCommon::get_record($tab, $id);
		if ($record) {
            $access = Utils_RecordBrowserCommon::get_access($tab, 'view', $record);
        }
		Base_AclCommon::set_user($old_user);
		return $access != false;
	}

	public static function get_records_with_new_notifications($user_id = null)
	{
		if (!$user_id) $user_id = Base_AclCommon::get_user();
		$sql = 'SELECT sub.internal_id,sub.category_id, ev.event_time FROM utils_watchdog_subscription AS sub
				LEFT JOIN (SELECT uwe.category_id, uwe.internal_id, MAX(id) as ev_id, MAX(uwe.event_time) as event_time FROM utils_watchdog_event AS uwe GROUP BY uwe.internal_id,uwe.category_id) AS ev
				ON sub.internal_id=ev.internal_id AND sub.category_id=ev.category_id
				WHERE sub.user_id=%d AND sub.last_seen_event<ev.ev_id ORDER BY ev.ev_id DESC';
		$records = DB::GetAll($sql, array($user_id));
		return $records;
	}
}

?>
