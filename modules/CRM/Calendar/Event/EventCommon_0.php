<?php
/**
 * Example event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventCommon extends Utils_Calendar_EventCommon {
	public static $filter = null;
	private static $my_id = null;

	public static function recurrence_type($i) {
		static $recurrence_numeric = null;
		static $recurrence_string = null;
		if(!isset($recurrence_numeric))
			$recurrence_numeric = array('everyday','second', 'third','fourth','fifth', 'sixth', 'week', 'week_custom', 'two_weeks', 'month','year');
		if(!isset($recurrence_string))
			$recurrence_string = array_flip($recurrence_numeric);
		if(is_numeric($i))
			return $recurrence_numeric[$i-1];
		return $recurrence_string[$i]+1;
	}

	public static function get_available_colors() {
		static $color = array(0 => '', 1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
		$color[0] = $color[Base_User_SettingsCommon::get('CRM_Calendar','default_color')];
		return $color;
	}

	public function get_emp_and_cus($id){
		$def = array();
		$def['cus_id'] = array();
		$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_cus WHERE id=%d', array($id));
		while ($row=$ret->FetchRow())
			$def['cus_id'][] = $row['contact'];
		$def['emp_id'] = array();
		$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_emp WHERE id=%d', array($id));
		while ($row=$ret->FetchRow())
			$def['emp_id'][] = $row['contact'];
		return $def;
	}

	public function get_followup_leightbox_href($id, $def){
		$prefix = 'crm_event_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);
		$v = $def['status'];
		if (!$v) $v = 0;
		if (isset($_REQUEST['form_name']) && $_REQUEST['form_name']==$prefix.'_follow_up_form' && $_REQUEST['id']==$id) {
			unset($_REQUEST['form_name']);
			$v = $_REQUEST['closecancel'];
			$action  = $_REQUEST['action'];
			if ($action == 'set_in_progress') $v = 1;
			DB::Execute('UPDATE crm_calendar_event SET status=%d WHERE id=%d',array($v,$id));
			Utils_WatchdogCommon::new_event('crm_calendar',$id,'Event status changed');
			if ($action == 'set_in_progress') location(array());

			$values = $def;
			$values['id'] = $id;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM/Calendar/Event','Follow up: ').$values['title'];
			$values['status'] = 0;

			if ($action != 'none') {
				$x = ModuleManager::get_instance('/Base_Box|0');
				$ec = CRM_Calendar_EventCommon::get_emp_and_cus($values['id']);
				$values['emp_id'] = $ec['emp_id'];
				$values['cus_id'] = $ec['cus_id'];
				if ($action == 'new_task') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('title'=>$values['title'],'permission'=>$values['access'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['emp_id'], 'customers'=>$values['cus_id'])), array('task'));
				if ($action == 'new_phonecall') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('subject'=>$values['title'],'permission'=>$values['access'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['emp_id'], 'contact'=>!empty($values['cus_id'])?array_pop($values['cus_id']):'')), array('phonecall'));
				if ($action == 'new_event') CRM_CalendarCommon::view_event('add',$values);
				return false;
			}

			location(array());
		}
		return 'href="javascript:void(0)" class="lbOn" rel="'.$prefix.'_followups_leightbox" onMouseDown="'.$prefix.'_set_id('.$id.');"';
	}

	//function prepares event array(required by Utils/Calendar) from database event row
	public static function parse_event($row) {
		$next_result = array();
		foreach (array('start','end','id','title','description','status') as $v)
			$next_result[$v] = $row[$v];
		if($row['timeless']) $next_result['timeless'] = date('Y-m-d',$row['start']);
		$next_result['duration'] = $row['end']-$row['start'];
		$color = self::get_available_colors();
		if($row['status']>=2)
			$next_result['color'] = 'gray';
		else
			$next_result['color'] = $color[$row['color']];

		static $access,$priority,$status;
		if(!isset($access)) {
			$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
			$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
			$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
		}
		$inf2 = array('Status' => $status[$row['status']],
						'Access' => $access[$row['access']],
						'Priority' => $priority[$row['priority']],
						'Notes' => Utils_AttachmentCommon::count('CRM/Calendar/Event/'.$row['id']));
		$next_result['additional_info2'] = '<hr>'.Utils_TooltipCommon::format_info_tooltip($inf2,'CRM_Calendar_Event').
						'<hr>'.CRM_ContactsCommon::get_html_record_info($row['created_by'],$row['created_on'],$row['edited_by'],$row['edited_on']);
		
		$emps_tmp = DB::GetCol('SELECT emp.contact FROM crm_calendar_event_group_emp AS emp WHERE emp.id=%d',array($row['id']));
		$cuss_tmp = DB::GetCol('SELECT cus.contact FROM crm_calendar_event_group_cus AS cus WHERE cus.id=%d',array($row['id']));

		$emps = array();
		foreach($emps_tmp as $k)
			$emps[] = CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($k));
		$cuss = array();
		foreach($cuss_tmp as $k)
			$cuss[] = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($k));
			
		$next_result['additional_info'] =  '<hr>'.Utils_TooltipCommon::format_info_tooltip(array(
				'Employees' => implode('<br>',$emps),
				'Customers' => implode('<br>',$cuss)),'CRM_Calendar_Event');
		$next_result['custom_agenda_col_0'] = $row['description'];
		$next_result['custom_agenda_col_1'] = implode(', ',$emps);
		$next_result['custom_agenda_col_2'] = implode(', ',$cuss);
		if($row['deleted']) {
			$next_result['edit_action'] = false;
			$next_result['move_action'] = false;
			$next_result['delete_action'] = false;
			$next_result['actions'] = array(array('icon'=>Base_ThemeCommon::get_template_file('CRM_Calendar_Event','restore_small.png'),'href'=>Module::create_href(array('restore'=>$next_result['id']))));
		} elseif($row['access']>0 && !in_array(self::$my_id,$emps_tmp) && !Base_AclCommon::i_am_admin()) {
			$next_result['edit_action'] = false;
			$next_result['move_action'] = false;
			$next_result['delete_action'] = false;
		} elseif($row['status']<2)
			$next_result['actions'] = array(array('icon'=>Base_ThemeCommon::get_template_file('CRM_Calendar_Event','access-private.png'),'href'=>CRM_Calendar_EventCommon::get_followup_leightbox_href($row['id'], $row)));

		if($row['recurrence_type'])
			$next_result['title'] = '<img src="'.Base_ThemeCommon::get_template_file('CRM_Calendar_Event','recurrence.png').'" border=0 hspace=0 vspace=0 align=left>'.$next_result['title'];

		return $next_result;
	}
	
	public static function get_next_recurrence_time($t,$row,$type=null,$time=null) {
		if($time===null)
			$time = date('H:i:s',strtotime(Base_RegionalSettingsCommon::time2reg($t,true,true,true,false)));
		if($type===null)
			$type = self::recurrence_type($row['recurrence_type']);
		switch($type) {
			case 'everyday':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*24);
				break;
			case 'second':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*48);
				break;
			case 'third':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*72);
				break;
			case 'fourth':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*96);
				break;
			case 'fifth':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*120);
				break;
			case 'sixth':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*144);
				break;
			case 'week':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*168);
				break;
			case 'week_custom':
				if (!$row['recurrence_hash']) {
					$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*168);
					break;
				}
				$date = strtotime(date('Y-m-d 12:00:00',$t));
				do {
					$date = strtotime(date('Y-m-d 12:00:00',$date+3600*24));
				} while(!$row['recurrence_hash']{date('N',$date)-1});
				$date = date('Y-m-d',$date);
				break;
			case 'two_weeks':
				$date = date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$t))+3600*168*2);
				break;
			case 'month':
				$year = date('Y',$t);
				$month = date('m',$t)%12+1;
				if($month==1) $year++;
				$date = date($year.'-'.$month.'-d',$t);
				break;
			case 'year':
				$year = date('Y',$t);
				$year++;
				$date = date($year.'-m-d',$t);
				break;
		}
		return strtotime($date.' '.date('H:i:s',Base_RegionalSettingsCommon::reg2time($date.' '.$time)));
	}

	public static function get_n_recurrence_time($t,$row,$n) {
		$time = date('H:i:s',strtotime(Base_RegionalSettingsCommon::time2reg($t,true,true,true,false)));
		$type = self::recurrence_type($row['recurrence_type']);
		while($n-->0) {
			$t = self::get_next_recurrence_time($t,$row,$type,$time);
		}
		return $t;
	}

	public static function get($id) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false)
			$id = substr($id,0,$recurrence);

		$fil = '';
		self::$my_id = CRM_FiltersCommon::get_my_profile();
		if(!Base_AclCommon::i_am_admin())
			$fil .= ' AND (e.access<2 OR (SELECT id FROM crm_calendar_event_group_emp cg2 WHERE cg2.id=e.id AND cg2.contact='.self::$my_id.' LIMIT 1) IS NOT NULL)';
		$t = microtime(true);
		$row = DB::GetRow('SELECT e.deleted,e.recurrence_type,e.status,e.color,e.access,e.starts as start,e.ends as end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on FROM crm_calendar_event e WHERE e.id=%d'.$fil,array($id));
		$result = array();
		if ($row) {
			$result = self::parse_event($row);
		}
		return $result;
	}
	
	public static function get_event_days($start,$end) {
		$start_reg = Base_RegionalSettingsCommon::reg2time($start);
		$end_reg = Base_RegionalSettingsCommon::reg2time($end);

		if(self::$filter=='()')
			$fil = ' AND 1=0';
		else if(self::$filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		self::$my_id = CRM_FiltersCommon::get_my_profile();
		if(!Base_AclCommon::i_am_admin())
			$fil .= ' AND (e.access<2 OR (SELECT id FROM crm_calendar_event_group_emp cg2 WHERE cg2.id=e.id AND cg2.contact='.self::$my_id.' LIMIT 1) IS NOT NULL)';
		if (DATABASE_DRIVER=='postgres') {
			$method_begin = '(SELECT TIMESTAMP \'epoch\' + ';
			$method_end = ' * INTERVAL \'1 second\')';
		} else {
			$method_begin = 'FROM_UNIXTIME(';
			$method_end = ')';
		}
		$ret = DB::Execute('SELECT e.timeless,e.recurrence_type,e.recurrence_hash,e.recurrence_end,e.color,e.starts as start FROM crm_calendar_event e WHERE e.status<1 AND deleted='.CRM_CalendarCommon::$trash.' AND ('.
			'(e.timeless=0 AND ((e.recurrence_type is null AND ((e.starts>=%d AND e.starts<%d) OR (e.ends>=%d AND e.ends<%d) OR (e.starts<%d AND e.ends>=%d))) OR (e.recurrence_type is not null AND ((e.starts>=%d AND e.starts<%d) OR (e.recurrence_end>=%D AND e.recurrence_end<%D) OR (e.starts<%d AND e.recurrence_end>=%D) OR (e.starts<%d AND e.recurrence_end is null))))) '.
			'OR '.
			'(e.timeless=1 AND ((e.recurrence_type is null AND DATE('.$method_begin.'e.starts'.$method_end.')>=%D AND DATE('.$method_begin.'e.starts'.$method_end.')<%D) OR (e.recurrence_type is not null AND ((DATE('.$method_begin.'e.starts'.$method_end.')<=%D AND e.recurrence_end>=%D) OR (DATE('.$method_begin.'e.starts'.$method_end.')>=%D AND DATE('.$method_begin.'e.starts'.$method_end.')<=%D) OR (e.recurrence_end>=%D AND e.recurrence_end<=%D) OR (e.starts<%d AND e.recurrence_end is null)))))) '.$fil,array($start_reg,$end_reg,$start_reg,$end_reg,$start_reg,$end_reg,$start_reg,$end_reg,$start,$end,$start_reg,$end,$end_reg,$start,$end,$start,$end,$start,$end,$start,$end,strtotime($end)));


		$last = array();
		while ($row = $ret->FetchRow()) {
			if($row['recurrence_type']) {
				$type = self::recurrence_type($row['recurrence_type']);
				if($row['timeless']) {
					if(isset($row['recurrence_end']))
						$rend = min(strtotime($row['recurrence_end']),$end);
					else
						$rend = strtotime($end);
				} else {
					if(isset($row['recurrence_end']))
						$rend = min(Base_RegionalSettingsCommon::reg2time($row['recurrence_end']),$end_reg);
					else
						$rend = $end_reg;
				}
				$kk = 0;
				if(($row['start']>=$start_reg && !$row['timeless']) || ($row['start']>=$start && $row['timeless'])) {
					if($type=='week_custom') {
						if($row['recurrence_hash']{date('N',strtotime(Base_RegionalSettingsCommon::time2reg($row['start'],false,true,true,false)))-1}) {
							if($row['timeless'])
								$next = date('Y-m-d',$row['start']);
							else
								$next = Base_RegionalSettingsCommon::time2reg($row['start'],false,true,true,false);
							if (!isset($last[$next])) {
								$last[$next] = $row['color'];
							}
						}
					} else {
						if($row['timeless'])
							$next = date('Y-m-d',$row['start']);
						else
							$next = Base_RegionalSettingsCommon::time2reg($row['start'],false,true,true,false);
						if (!isset($last[$next])) {
							$last[$next] = $row['color'];
						}
					}
				}
				$start_time = date('H:i:s',strtotime(Base_RegionalSettingsCommon::time2reg($row['start'],true,true,true,false)));
				while($row['start']<$rend) {
						$kk++;
						$row['start'] = self::get_next_recurrence_time($row['start'],$row,$type,$start_time);
						if((($row['start']>=$start_reg && !$row['timeless']) || ($row['start']>=$start && $row['timeless'])) && $row['start']<$rend) {
							if($row['timeless'])
								$next = date('Y-m-d',$row['start']);
							else
								$next = Base_RegionalSettingsCommon::time2reg($row['start'],false,true,true,false);
							if (!isset($last[$next])) {
								$last[$next] = $row['color'];
							}
						}
				}
			} else {
				if($row['timeless'])
					$next = date('Y-m-d',$row['start']);
				else
					$next = Base_RegionalSettingsCommon::time2reg($row['start'],false,true,true,false);
				if (isset($last[$next])) continue;
				$last[$next] = $row['color'];
			}
		}
		return $last;
	}

	public static function get_all($start,$end,$order=' ORDER BY e.starts') {
		if(isset($_GET['restore']) && is_numeric($_GET['restore'])) 
			self::restore_event($_GET['restore']);
		//trigger_error($start.' '.$end);
		$start_reg = Base_RegionalSettingsCommon::reg2time($start);
		$end_reg = Base_RegionalSettingsCommon::reg2time($end);
		if(self::$filter=='()')
			$fil = ' AND 1=0';
		else if(self::$filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		self::$my_id = CRM_FiltersCommon::get_my_profile();
		if(!Base_AclCommon::i_am_admin())
			$fil .= ' AND (e.access<2 OR (SELECT id FROM crm_calendar_event_group_emp cg2 WHERE cg2.id=e.id AND cg2.contact='.self::$my_id.' LIMIT 1) IS NOT NULL)';
		if (DATABASE_DRIVER=='postgres') {
			$method_begin = '(SELECT TIMESTAMP \'epoch\' + ';
			$method_end = ' * INTERVAL \'1 second\')';
		} else {
			$method_begin = 'FROM_UNIXTIME(';
			$method_end = ')';
		}
		$ret = DB::Execute('SELECT e.deleted,e.recurrence_type,e.recurrence_hash,e.recurrence_end,e.status,e.color,e.access,e.starts as start,e.ends as end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on FROM crm_calendar_event e WHERE deleted='.CRM_CalendarCommon::$trash.' AND ('.
			'(e.timeless=0 AND ((e.recurrence_type is null AND ((e.starts>=%d AND e.starts<%d) OR (e.ends>=%d AND e.ends<%d) OR (e.starts<%d AND e.ends>=%d))) OR (e.recurrence_type is not null AND ((e.starts>=%d AND e.starts<%d) OR (e.recurrence_end>=%D AND e.recurrence_end<%D) OR (e.starts<%d AND e.recurrence_end>=%D) OR (e.starts<%d AND e.recurrence_end is null))))) '.
			'OR '.
			'(e.timeless=1 AND ((e.recurrence_type is null AND DATE('.$method_begin.'e.starts'.$method_end.')>=%D AND DATE('.$method_begin.'e.starts'.$method_end.')<%D) OR (e.recurrence_type is not null AND ((DATE('.$method_begin.'e.starts'.$method_end.')<=%D AND e.recurrence_end>=%D) OR (DATE('.$method_begin.'e.starts'.$method_end.')>=%D AND DATE('.$method_begin.'e.starts'.$method_end.')<=%D) OR (e.recurrence_end>=%D AND e.recurrence_end<=%D) OR (e.starts<%d AND e.recurrence_end is null)))))) '.$fil.$order.' LIMIT 51',array($start_reg,$end_reg,$start_reg,$end_reg,$start_reg,$end_reg,$start_reg,$end_reg,$start,$end,$start_reg,$end,$end_reg,$start,$end,$start,$end,$start,$end,$start,$end,strtotime($end)));
		$result = array();
		$count = 0;
		while ($row = $ret->FetchRow()) {
			$next_result = self::parse_event($row);
			
			if($row['recurrence_type']) {
				$type = self::recurrence_type($row['recurrence_type']);
				if($row['timeless']) {
					if(isset($row['recurrence_end'])) {
						$rend = min(strtotime($row['recurrence_end'].' 12:00:00')+3600*24,$end);
					} else
						$rend = strtotime($end);
				} else {
					if(isset($row['recurrence_end'])) {
						$rend = min(Base_RegionalSettingsCommon::reg2time(strtotime($row['recurrence_end'].' 12:00:00')+3600*24),$end_reg);
					} else
						$rend = $end_reg;
				}
				$kk = 0;
				if(($next_result['start']>=$start_reg && !$row['timeless']) || ($next_result['start']>=$start && $row['timeless'])) {
					$next_result['id'] = $row['id'].'_'.$kk;
					if($type=='week_custom') {
						if($row['recurrence_hash']{date('N',strtotime(Base_RegionalSettingsCommon::time2reg($next_result['start'],false,true,true,false)))-1})
							$result[] = $next_result;
					} else {
						$result[] = $next_result;
					}
				}
				$start_time = date('H:i:s',strtotime(Base_RegionalSettingsCommon::time2reg($next_result['start'],true,true,true,false)));
				$end_time = date('H:i:s',strtotime(Base_RegionalSettingsCommon::time2reg($next_result['end'],true,true,true,false)));
				while($next_result['start']<$rend) {
						$kk++;
						$next_result['id'] = $row['id'].'_'.$kk;
						$next_result['start'] = self::get_next_recurrence_time($next_result['start'],$row,$type,$start_time);
						$next_result['end'] = self::get_next_recurrence_time($next_result['end'],$row,$type,$end_time);
						if(isset($next_result['timeless'])) $next_result['timeless'] = date('Y-m-d',$next_result['start']);
						if((($next_result['start']>=$start_reg && !$row['timeless']) || ($next_result['start']>=$start && $row['timeless'])) && $next_result['start']<$rend) {
							$result[] = $next_result;
						}
				}
			} else {
				$count++;
				if($count>50) {
					Epesi::alert(Base_LangCommon::ts('CRM_Calendar_Event','Too much events to display - displaying only 50.',array($count)));
					break;
				}
				$result[] = $next_result;
			}
		}
		return $result;
	}

	public static function delete($id) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false) {
			$id = substr($id,0,$recurrence);
			print('Epesi.updateIndicatorText("updating calendar");Epesi.request("");');
		}

		if(!self::check_edit_access($id)) return false;

		/*DB::Execute('DELETE FROM crm_calendar_event_group_emp WHERE id=%d', array($id));
		DB::Execute('DELETE FROM crm_calendar_event_group_cus WHERE id=%d', array($id));
		DB::Execute('DELETE FROM crm_calendar_event WHERE id=%d',array($id));*/
		DB::Execute('UPDATE crm_calendar_event SET deleted=1, edited_on=%T, edited_by=%d WHERE id=%d',array(time(),Acl::get_user(),$id));
		//Utils_AttachmentCommon::persistent_mass_delete('CRM/Calendar/Event/'.$id);
		//Utils_MessengerCommon::delete_by_id('CRM_Calendar_Event:'.$id);
		Utils_WatchdogCommon::user_unsubscribe(null, 'crm_calendar', $id);

		if($recurrence!==false)
			return false;
		return true;
	}
	
	private static function check_edit_access($id) {
		$row = DB::GetRow('SELECT access,deleted FROM crm_calendar_event WHERE id=%d',array($id));
		$access = $row['access'];
		if($row['deleted'])
			return false;
		if($access > 0) {
			self::$my_id = CRM_FiltersCommon::get_my_profile();
			$ok = DB::GetOne('SELECT 1 FROM crm_calendar_event_group_emp WHERE id=%d AND contact=%d',array($id,self::$my_id));
			if(!$ok) return false;
		}
		return true;
	}

	public static function update(&$id,$start,$duration,$timeless) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false) {
			$id = substr($id,0,$recurrence);
			print('Epesi.updateIndicatorText("updating calendar");Epesi.request("");');
		}

		if(!self::check_edit_access($id)) return false;

		if($timeless) {
			$start = strtotime(date('Y-m-d',$start));
			$duration = 0;
		}

		DB::Execute('UPDATE crm_calendar_event SET starts=%d, ends=%d, timeless=%b, edited_by=%d, edited_on=%T WHERE id=%d',array($start,$start+$duration,$timeless,Acl::get_user(),date('Y-m-d H:i:s'),$id));
		Utils_WatchdogCommon::new_event('crm_calendar',$id,'Event moved');

		if($recurrence!==false)
			return false;
		return true;
	}

	public static function get_alarm($id) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false)
			$id = substr($id,0,$recurrence);

		$a = self::get($id);

		if (!$a) return Base_LangCommon::ts('CRM_Calendar_Event','Private record');

		if(isset($a['timeless']))
			$date = Base_LangCommon::ts('CRM_Calendar_Event','Timeless event: %s',array(Base_RegionalSettingsCommon::time2reg($a['timeless'],false)));
		else
			$date = Base_LangCommon::ts('CRM_Calendar_Event',"Start: %s\nEnd: %s",array(Base_RegionalSettingsCommon::time2reg($a['start']), Base_RegionalSettingsCommon::time2reg($a['end'])));

		return $date."\n".Base_LangCommon::ts('CRM_Calendar_Event',"Title: %s",array($a['title']));
	}
	
	public static function restore_event($id) {
		DB::Execute('UPDATE crm_calendar_event SET deleted=0, edited_by=%d,edited_on=%T WHERE id=%d',array(Acl::get_user(),time(),$id));
	}
}

?>
