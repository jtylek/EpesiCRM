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
	//
	public function add_event_type($module_name, $title, $description) {
		return DB::Execute("INSERT INTO calendar_event(module_name, title, description) VALUES(%s, %s, %s)", array($module_name, $title, $description));
	}
	public function remove_event_type($module_name) {
		return DB::Execute('delete from calendar_event where module_name=%s', array($module_name));
	}
	///////////////////////////////////////////////////////////////////////////
	public function get_event_types() {
		$ret = DB::Execute('select module_name, title, description from calendar_event');
		$list = array();
		while($row = $ret->FetchRow()) {
			$list[] = array(
				'module_name' => $row['module_name'],
				'title' => $row['title'],
				'description' => $row['description']
			);
		}
		return $list;
	}
	///////////////////////////////////////////////////////////////////////////////////
	// structure should be always like this:
	// $events[>module name<] = array(
	//			'module_name' => >module name<,
	//			'title' => >module title as provided by module<,
	//			'description' => >desc provided by module<,
	//			'events' => array(
	//				'regular' => array indexed by day, hour and then by id
	//				'timeless' => array indexed by day and then by id
	//			)
	//		);
	//	TODO: indices should include full date, since now it is different in every method
	
	
	public function get_month( $date ) {
		$list = self::get_event_types();
		$events = array();
		foreach($list as $module) {
			$events[$module['module_name']] = array(
				'module_name' => $module['module_name'],
				'title' => $module['title'],
				'description' => $module['description'],
				'events' => call_user_func(array($module['module_name'].'Common', 'get_month'), $date)
			);
		}
		return $events;
	}
	
	public function get_day($date) {
		$list = self::get_event_types();
		$events = array();
		foreach($list as $module) {
			$events[$module['module_name']] = array(
				'module_name' => $module['module_name'],
				'title' => $module['title'],
				'description' => $module['description'],
				'events' => call_user_func(array($module['module_name'].'Common', 'get_day'), $date)
			);
		}
		return $events;
	}	
	public function get_7days($date) {
		$list = self::get_event_types();
		$events = array();
		foreach($list as $module) {
			$local_events = call_user_func(array($module['module_name'].'Common', 'get_7days'), $date);
			$events[$module['module_name']] = array(
				'module_name' => $module['module_name'],
				'title' => $module['title'],
				'description' => $module['description'],
				'events' => $local_events
			);
		}
		return $events;
	}
	public function get_week($date) {
		$list = self::get_event_types();
		$events = array();
		foreach($list as $module) {
			$local_events = call_user_func(array($module['module_name'].'Common', 'get_week'), $date);
			$events[$module['module_name']] = array(
				'module_name' => $module['module_name'],
				'title' => $module['title'],
				'description' => $module['description'],
				'events' => $local_events
			);
		}
		return $events;
	}
}
?>