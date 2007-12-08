<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventCommon extends ModuleCommon {

	public static function make_containment_id($year, $month, $day, $hour = 'tt') {
		if($hour === 'tt')
			//return sprintf( '%4dL%02dL%02dLtt',$year, $month, $day);
			return sprintf( '%4d%02d%02dtt', $year, $month, $day);
		else
			return sprintf( '%4d%02d%02d%02d', $year, $month, $day, $hour);
	}

/*
	 * True if event is assigned to a specific day.
	 */
	private static function select_all() {
		return
			'select '.
			'	id, '. 
			'	employees,  '.
			'	contacts,  '.
			'	round(datetime_start)+0 as datetime_start, '.
			'	round(datetime_end)+0 as datetime_end, '.
			'	title, '.
			'	description, '.
			'	act_id, '.
			'	created_by, '.
			'	created_on, '.
			'	edited_by, '.
			'	edited_on, '.
			'	access, '.
			'	timeless '.
			'from calendar_events ';
	}
	
	public static function is_dragable() {
		return true;
	}
	/*
	 * True if event has start and end hours.
	 */
	public static function is_expandable() {
		return true;
	}
	
	public static function decode_contact($id) {
		if(isset( $id )) {
			$contact = CRM_ContactsCommon::get_contact($id);
			return $contact['First Name']." ".$contact['Last Name'];
		} else {
			return '';
		}
	}
	public static function decode_login_or_contact($id) {
		$emp = self::decode_contact($id);
		if( $emp === '' ) {
			//print $id."<br>";
			$set = DB::Execute("select login from user_login where id=%d", $id);
			if($set) {
				if($row = $set->FetchRow()) {
					$emp = $row['login'];
				}
			}
			return $emp;
		} else {
			return $emp;
		}
	}
	
	public static function decode_activity($id) {
		if(isset( $id )) {
			$act = '';
			$set = DB::Execute("select name from calendar_events where id=%d", $id);
			if($set ) {
				if($row = $set->FetchRow()) {
					$act = $row['name'];
				}
			}
			return $act;
		} else {
			return array();
		}
	}

	/////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Get events
	public static function get_text($row, $style = 0) {

		return;

		$emps = self::decode_group($row['employees']);
		if(!empty($emps))
			$emps = join(', ', $emps);
		else
			$emps = '';
		$cuss = self::decode_group($row['contacts']);
		if(!empty($emps))
			$cuss = join(', ', $cuss);
		else
			$cuss = '';
		$acts = CRM_Calendar_Event_Common::decode_activity($row['act_id']);
		$time = substr($row['datetime_start'], 8, 2).':'.substr($row['datetime_start'], 10, 2);
		$finish = substr($row['datetime_end'], 8, 2).':'.substr($row['datetime_end'], 10, 2);
		
		
		if(Base_RegionalSettingsCommon::time_12h()) {
			$time = strtotime($time);
			$format = '%I:%M %p';
			$time =strftime($format,$time);
			$finish = strtotime($finish);
			$finish = strftime($format,$finish);
		}
		
		$time = '<span name="event'.$row['id'].'start">'.$time.'</span>';
		$divider = '<span name="event'.$row['id'].'divider"> - </span>';
		$finish = '<span name="event'.$row['id'].'finish">'.$finish.'</span>';
		$after = '<span name="event'.$row['id'].'after">: </span>';
		if($row['timeless'] == 1) {
			$time = '<span name="event'.$row['id'].'start"></span>';
			$divider = '<span name="event'.$row['id'].'divider"></span>';
			$finish = '<span name="event'.$row['id'].'finish"></span>';
			$after = '<span name="event'.$row['id'].'after"></span>';
		}
		switch($style) {
			case 'time':
				//return $time.$divider.$finish;
				return '<b>'.$time.'</b>';
			case 'title':
				if($row['created_by'] == Acl::get_user() || $row['access'] <= 1)
					return $row['title'];
				else
					return '<font color=red>'.Base_UserCommon::get_user_login($row['created_by']).' PRIVATE</font>';
			//--------------------------------------------------------
			case 'brief':
				if($row['created_by'] == Acl::get_user() || $row['access'] <= 1)
					return '<font size=1 face=tahoma><b>'.$time.$after.'</b>'.$row['title'];
				else
					return '<font size=1 face=tahoma><b>'.$time.$after.'</b><font color=red>'.Base_UserCommon::get_user_login($row['created_by']).' PRIVATE</font>';
			//----------------------------------------	
			case 'agenda':
				if($row['created_by'] == Acl::get_user() || $row['access'] <= 1)
					return "<b>".$row['title']."</b> -- <u>".$acts."</u>".$emps;
				else
					return "<b>".Base_UserCommon::get_user_login($row['created_by']).' PRIVATE</b>';
			//----------------------------------------	
			case 'line':
				if($row['created_by'] == Acl::get_user() || $row['access'] <= 1)
					return "<b>".$time.$divider.$finish.$after.$row['title']."</b> -- <u>".$acts."</u>".$emps;
				else
					return "<b>".$time.$divider.$finish."</b> -- ".Base_UserCommon::get_user_login($row['created_by']).' PRIVATE';
			//-----------------------------------------
			case 'edit':
				$edits = array('created_by'=>'created_on', 'edited_by'=>'edited_on');
				$more = '<table>';
				foreach($edits as $who=>$when) {
					if(CRM_Calendar_Utils_FuncCommon::get_settings('show_detail_'.$who) == 1) {
						if(isset($row[$who]) && $row[$who] !== '') {
							$more .= '<tr><td style=\'vertical-align: top\'><u>'.str_replace('_', ' ', ucfirst($who)).'</u>:</td><td>'.Base_UserCommon::get_user_login($row[$who]);
						
							if(CRM_Calendar_Utils_FuncCommon::get_settings('show_detail_'.$when) == 1)
								if(isset($row[$when]) && $row[$when] !== '')
									$more .= '<br>on '.$row[$when];
							$more .= '</tr>';
						}
					}
				}
				$more .= '</table>';
				if($row['created_by'] == Acl::get_user() || $row['access'] <= 1)
					return '<b>'.$time.$divider.$finish.$after.$row['title'].'</b>'.$more;
				else
					return "<b>".$time.$divider.$finish.'</b><br>'.Base_UserCommon::get_user_login($row['created_by']).' PRIVATE';
				break;
			case 'full':
			case 0:
			default:
				$row['activity'] = $acts;
				$row['employees'] = $emps;
				$row['customers'] = $cuss;
				$fields = array('activity', 'employees', 'customers', 'description', 'access', 'priority');
				$full = '<table>';
				foreach($fields as $f) {
					if(CRM_Calendar_Utils_FuncCommon::get_settings('show_detail_'.$f) == 1) {
						if(isset($row[$f]) && $row[$f] !== '')
							$full .= '<tr><td style=\'vertical-align: top\'><u>'.str_replace('_', ' ', ucfirst($f)).'</u>:</td><td>'.$row[$f].'</td></tr>';
					}
				}
				$full .= '</table>';
				
				if($row['created_by'] == Acl::get_user() || $row['access'] <= 1)
					return '<b>'.$time.$divider.$finish.$after.$row['title'].'</b>'.$full;
				else
					return "<b>".$time.$divider.$finish.'</b><br>'.Base_UserCommon::get_user_login($row['created_by']).' PRIVATE';
				
		}
	}
	
	public static function get_month( $date ) {
		$logged = -1;
		if(Acl::is_user())
			$logged = Acl::get_user();
			
		$ret = null;
		if(Base_AclCommon::i_am_admin() && CRM_Calendar_Utils_FuncCommon::get_settings('show_private'))
			$ret = DB::Execute(
				self::select_all().
				"where round(datetime_start) like %s  and status=1 order by datetime_start asc", 
			array(sprintf("%04d%02d%%", $date['year'], $date['month'])));
		else
			$ret = DB::Execute(
				self::select_all().
				"where round(datetime_start) like %s and (created_by=%d or access=0) and status=1 order by datetime_start asc", 
			array(sprintf("%04d%02d%%", $date['year'], $date['month']), $logged, $logged));
		
		$events = array();
		if($ret) {
			while($row = $ret->FetchRow()) {
				//print $row['datetime_start']." - ".$row['datetime_end']."<br>";
				$d = substr($row['datetime_start'], 6, 2);
				$t = substr($row['datetime_start'], 8, 4);
				$id = $row['id'];
				if(!isset($events[$d]))
					$events[$d] = array();
				if(!isset($events[$d][$t]))
					$events[$d][$t] = array();
				if(!isset($events[$d][$t][$id]))
					$events[$d][$t][$id] = $row;
			}
		}
		return $events;
	}
			
	public static function get_agenda($start, $end) {
		if(Base_AclCommon::i_am_user()) {
			$year = $start['year'];
			$today = CRM_Calendar_Utils_FuncCommon::today();
			$datetime_start = $start;
			$datetime_end = $end;
			$datetime_start = Base_RegionalSettingsCommon::server_date($datetime_start);
			$datetime_end = Base_RegionalSettingsCommon::server_date($datetime_end);
			if(is_array($start))
				$datetime_start = sprintf("%d%02d%02d000000", $start['year'],$start['month'],$start['day']);
			
			if(is_array($end))
				$datetime_end = sprintf("%d%02d%02d999999", $end['year'],$end['month'],$end['day']);
			
			//print $datetime_start;
			$logged = Acl::get_user();
			if(Base_AclCommon::i_am_admin() && CRM_Calendar_Utils_FuncCommon::get_settings('show_private'))
				$ret = DB::Execute(
					self::select_all().
					" where (datetime_start between %T and %T) and timeless=0 and status=1", 
					array($datetime_start, $datetime_end));
			else
				$ret = DB::Execute(
					self::select_all().
					" where (datetime_start between %T and %T) and (created_by=%d or access=0) and timeless=0 and status=1", 
					array($datetime_start, $datetime_end, $logged, $logged));
			
			$events = array();
			if($ret) {
				while($row = $ret->FetchRow()) {
					$ds = $row['datetime_start'];
					$id = $row['id'];
					if(!isset($events[$id]))
						$events[$id] = $row;
				}
			}
			
			if(Base_AclCommon::i_am_admin() && CRM_Calendar_Utils_FuncCommon::get_settings('show_private'))
				$ret = DB::Execute(self::select_all().
					" where (datetime_start between %T and %T) and timeless=1 and status=1", 
					array($datetime_start, $datetime_end));
			else
				$ret = DB::Execute(self::select_all().
					" where (datetime_start between %T and %T) and (created_by=%d or access=0) and timeless=1 and status=1 ", 
					array($datetime_start, $datetime_end, $logged, $logged));
			
			$timeless = array();
			if($ret) {
				while($row = $ret->FetchRow()) {
					$ds = $row['datetime_start'];
					$id = $row['id'];
					if(!isset($events[$id]))
						$timeless[$id] = $row;
				}
			}
			
			return array('regular'=>$events, 'timeless'=>$timeless);
		} else {
			return array('regular'=>array(), 'timeless'=>array());
		}
	}

	public static function get_day($date, $eference = null) {
		if(Base_AclCommon::i_am_user()) {
			$datetime_start = sprintf("%d%02d%02d%%", $date['year'], $date['month'], $date['day']);
			
			$logged = Acl::get_user();
			if(Base_AclCommon::i_am_admin() && CRM_Calendar_Utils_FuncCommon::get_settings('show_private'))
				$ret = DB::Execute(
					self::select_all().
					"where (round(datetime_start)+0 like %s) and timeless=0 and status=1", 
					array($datetime_start));
			else
				$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (round(datetime_start)+0 like %s) and (created_by=%d or access=0) and timeless=0 and status=1", array($datetime_start, $logged));
	
			$events = array();
			if($ret) {
				while($row = $ret->FetchRow()) {
					$h = substr($row['datetime_start'], 8, 2);
					$m = substr($row['datetime_start'], 10, 2);
					$id = $row['id'];
					if(!isset($events[$h]))
						$events[$h] = array();
					if(!isset($events[$h][$m]))
						$events[$h][$m] = array();
					if(!isset($events[$h][$m][$id]))
						$events[$h][$m][$id] = $row;
				}
			}
			
			if(Base_AclCommon::i_am_admin() && CRM_Calendar_Utils_FuncCommon::get_settings('show_private'))
				$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (round(datetime_start)+0 like %s) and timeless=1 and status=1", array($datetime_start));
			else
				$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (round(datetime_start)+0 like %s) and (created_by=%d or access=0) and timeless=1 and status=1", array($datetime_start, $logged));
			$timeless = array();
			if($ret) {
				while($row = $ret->FetchRow()) {
					$d = substr($row['datetime_start'], 6, 2);
					$id = $row['id'];
					if(!isset($events[$id]))
						$timeless[$id] = $row;
				}
			}
			
			return array('regular'=>$events, 'timeless'=>$timeless);
		} else {
			return false;
		}
	}
	
	public static function get_7days($start, $regular_week = 0) {
		if(Base_AclCommon::i_am_user()) {
			$year = $start['year'];
			$today = CRM_Calendar_Utils_FuncCommon::today();
			$datetime_start = sprintf("%d%02d%02d000000", $start['year'],$start['month'],$start['day']);
			
			$end = CRM_Calendar_Utils_FuncCommon::next_day($start, 7);
			$datetime_end = sprintf("%d%02d%02d999999", $end['year'],$end['month'],$end['day']);
			
			$logged = Acl::get_user();
			$ret = null;
			if(Base_AclCommon::i_am_admin() && CRM_Calendar_Utils_FuncCommon::get_settings('show_private'))
				$ret = DB::Execute("select ".
				"id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, ".
				"title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless ".
				"from calendar_events as p join calendar_event_personal_group as g on p.employees=g.gid ".
				"where (datetime_start+0>=%s and datetime_start+0<=%s) and timeless=0 and status=1", 
				array($datetime_start, $datetime_end));
			else
				$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (datetime_start+0>=%s and datetime_start+0<=%s) and (created_by=%d or access=0 or access=1) and timeless=0 and status=1", array($datetime_start, $datetime_end, $logged));
			
			if($ret) {
				while($row = $ret->FetchRow()) {
					$dh = substr($row['datetime_start'], 6, 4);
					$m = substr($row['datetime_start'], 10, 2);
					$id = $row['id'];
					if(!isset($events[$dh]))
						$events[$dh] = array();
					if(!isset($events[$dh][$m]))
						$events[$dh][$m] = array();
					if(!isset($events[$dh][$m][$id]))
						$events[$dh][$m][$id] = $row;
				}
			}
			
			$ret = null;
			if(Base_AclCommon::i_am_admin() && CRM_Calendar_Utils_FuncCommon::get_settings('show_private'))
				$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (datetime_start+0>=%s and datetime_start+0<=%s) and timeless=1 and status=1", array($datetime_start, $datetime_end));
			else
				$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (datetime_start+0>=%s and datetime_start+0<=%s) and (created_by=%d or access=0 or access=1) and timeless=1 and status=1", array($datetime_start, $datetime_end, $logged));
			
			$timeless = array();
			if($ret) {
				while($row = $ret->FetchRow()) {
					$d = (int)substr($row['datetime_start'], 6, 2);
					$id = $row['id'];
					if(!isset($timeless[$d]))
						$timeless[$d] = array();
					if(!isset($events[$d][$id]))
						$timeless[$d][$id] = $row;
				}
			}
			
			return array('regular'=>$events, 'timeless'=>$timeless);
		} else {
			return array('regular'=>array(), 'timeless'=>array());
		}
	}
	public static function get_week($date, $regular_week = 0) {
		if(Base_AclCommon::i_am_user()) {
			$week = $date['week'];
			$year = $date['year'];
			$today = CRM_Calendar_Utils_FuncCommon::today();
			$start = CRM_Calendar_Utils_FuncCommon::begining_of_week($year, $week);
			$datetime_start = sprintf("%d%02d%02d000000", $start['year'],$start['month'],$start['day']);
			
			$end = CRM_Calendar_Utils_FuncCommon::ending_of_week($year, $week);
			$datetime_end = sprintf("%d%02d%02d999999", $end['year'],$end['month'],$end['day']);
			
			$logged = Acl::get_user();
			$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (datetime_start+0>=%s and datetime_start+0<=%s) and (created_by=%d or access=0) and timeless=0 and status=1", array($datetime_start, $datetime_end, $logged));
			
			$events = array();
			if($ret) {
				while($row = $ret->FetchRow()) {
					$dh = substr($row['datetime_start'], 6, 4);
					$m = substr($row['datetime_start'], 10, 2);
					$id = $row['id'];
					if(!isset($events[$dh]))
						$events[$dh] = array();
					if(!isset($events[$dh][$m]))
						$events[$dh][$m] = array();
					if(!isset($events[$dh][$m][$id]))
						$events[$dh][$m][$id] = $row;
				}
			}
			
			$ret = DB::Execute("select id, employees, contacts, round(datetime_start)+0 as datetime_start, round(datetime_end)+0 as datetime_end, title, description,  act_id, created_by, created_on, edited_by, edited_on,  access, timeless from calendar_events where (datetime_start+0>=%s and datetime_start+0<=%s) and (created_by=%d or access=0) and timeless=1 and status=1", array($datetime_start, $datetime_end, $logged));
			$timeless = array();
			if($ret) {
				while($row = $ret->FetchRow()) {
					$d = substr($row['datetime_start'], 6, 2);
					$id = $row['id'];
					if(!isset($timeless[$d]))
						$timeless[$d] = array();
					if(!isset($events[$d][$id]))
						$timeless[$d][$id] = $row;
				}
			}
			
			return array('regular'=>$events, 'timeless'=>$timeless);
		} else {
			return false;
		}
	}
}
?>