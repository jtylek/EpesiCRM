<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Utils_FuncCommon extends ModuleCommon {
	private static $settings = array();
	private static $array_name_of_month = array(
		1 => array(
			1=>'Jan', 
			2=>'Feb', 
			3=>'Mar', 
			4=>'Apr', 
			5=>'May', 
			6=>'Jun', 
			7=>'Jul', 
			8=>'Aug', 
			9=>'Sep', 
			10=>'Oct', 
			11=>'Nov', 
			12=>'Dec',
			'01'=>'Jan', 
			'02'=>'Feb', 
			'03'=>'Mar', 
			'04'=>'Apr', 
			'05'=>'May', 
			'06'=>'Jun', 
			'07'=>'Jul', 
			'08'=>'Aug', 
			'09'=>'Sep'
		),
		2 => array(
			1=>'January', 
			2=>'February', 
			3=>'March', 
			4=>'April', 
			5=>'May', 
			6=>'June', 
			7=>'July', 
			8=>'August', 
			9=>'September', 
			10=>'October', 
			11=>'November', 
			12=>'December',
			'01'=>'January', 
			'02'=>'February', 
			'03'=>'March', 
			'04'=>'April', 
			'05'=>'May', 
			'06'=>'June', 
			'07'=>'July', 
			'08'=>'August', 
			'09'=>'September'
		)
	);
	private static $array_name_of_day = array(
		0 => array(
				0=>'S', 1=>'M', 2=>'T', 3=>'W', 4=>'T', 5=>'F', 6=>'S'
			),
		1 => array(
				0=>'Sun', 1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri', 6=>'Sat'
			),
		2 => array(
				0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday'
			)
		);
	private static $available_view_style = array('month'=>'Month View', 'week'=>'Week View', 'nearest'=>'Nearest Events');
		
	public static function get_settings($field) {
		if(!isset(self::$settings[$field]))
			self::$settings[$field] = Base_User_SettingsCommon::get('CRM/Calendar', $field);
		return self::$settings[$field];
	}
	public static function get_common_settings() {
		$ret = array('asasa'=>array());
		$views = array('Day', 'Week', 'Month', 'Year', 'Agenda');
		$prefix = 'CRM_Calendar_View_';
		foreach($views as $view) {
			if(class_exists('CRM_Calendar_View_WeekCommon'))
				print $prefix.$view.'Common:: ';
			if(class_exists($prefix.$view.'Common') && method_exists($prefix.$view.'Common', 'calendar_user_settings')) {
				print 'terefere';
				$settings = call_user_func($prefix.$view.'Common', 'calendar_user_settings');
				if(!empty($settings)) {
					$ret[$view] = $settings;
				}
			}
		}
		return $ret;
	}
	/////////////////////////////////////////////////////////////////////////////
	// DATE-COUNTING RELATED FUNCTIONS 
	/////////////////////////////////////////////////////////////////////////////
	/**
	 * style: 
	 * 		0 - 1 letter
	 * 		1 - 3 letters
	 * 		2 - full word
	 */
	
	public static function name_of_month( $idx, $style = 1 ) {
		return self::$array_name_of_month[$style][$idx];
	}
	//--------------------------------
	public static function name_of_day( $idx, $style = 1 ) {
		$idx = CRM_Calendar_Utils_FuncCommon::translate_index($idx);
		return self::$array_name_of_day[$style][$idx];
	}
	//////////////////////////////////////
	public static function today() {
		$week = self::week_of_year_r(array('year'=>date("Y"), 'month'=>date("m"),'day'=>date("d")));
		return array('year'=>date("Y"), 'month'=>date("m"), 'day'=>date("d"), 'week'=>$week);
	}
	public static function is_today($date) {
		$is = false;
		$today = array('year'=>date("Y"), 'month'=>date("m"), 'day'=>date("d"));
		if(intval($date['year']) == intval($today['year']) && intval($date['month']) == intval($today['month']) && intval($date['day']) == intval($today['day']))
			$is = true;
		return $is;
	}
	public static function is_today_r($year, $month, $day) {
		$is = false;
		$today = array('year'=>date("Y"), 'month'=>date("m"), 'day'=>date("d"));
		if(intval($year) == intval($today['year']) && intval($month) == intval($today['month']) && intval($day) == intval($today['day']))
			$is = true;
		return $is;
	}
	
	public static function next_day($date, $more = 1) {
		if(!isset($date['year']) || !isset($date['month']) || !isset($date['day'])) 
			return self::today();
		$next = array(
			'year' => $date['year'],
			'month' => $date['month'],
			'day' => ($date['day']+1)
		);
		if($next['day'] > self::days_in_month_r($date)) {
			$next['day'] = 1;
			$next['month']++;
			if($next['month'] > 12) {
				$next['month'] = 1;
				$next['year']++;
			}
		}
		if($more == 1)
			return $next;
		else
			return self::next_day($next, $more-1);
	}
	public static function prev_day($date, $more = 1) {
		if(!isset($date['year']) || !isset($date['month']) || !isset($date['day'])) 
			return self::today();
		$prev = array(
			'year' => $date['year'],
			'month' => $date['month'],
			'day' => ($date['day']-1)
		);
		if($prev['day'] < 1) {
			$prev['month']--;
			if($prev['month'] < 1) {
				$prev['month'] = 12;
				$prev['year']--;
			}
			$prev['day'] = self::days_in_month_r($prev);
		}
		if($more == 1)
			return $prev;
		else
			return self::prev_day($prev, $more-1);
	}
	// next/prev month -------------------------------------------------
	public static function next_month($date, $more = 1) {
		if(!isset($date['year']) || !isset($date['month']) || !isset($date['day'])) 
			return self::today();
		$next = array(
			'year' => $date['year'] + (int)(($date['month'] + $more - 0.5) / 12),
			'month' => (($date['month'] + ($more % 12) - 1) % 12) + 1,
			'day' => 1
		);
		return $next;
	}
	public static function prev_month($date, $more = 1) {
		if(!isset($date['year']) || !isset($date['month']) || !isset($date['day'])) 
			return self::today();
		$prev = array(
			'year' => $date['year'] - (int)((12 - $date['month'] + $more + 0.5) / 12),
			'month' => (($date['month'] + ((12-$more) % 12) - 1) % 12) + 1,
			'day' => 1
		);
		return $prev;
	}
	// next/prev week -------------------------------------------------
	public static function next_week($date, $more = 1) {
		return self::begining_of_week_r(self::next_day($date, $more*7 ));
	}
	public static function prev_week($date, $more = 1) {
		return self::begining_of_week_r(self::prev_day($date, $more*7 ));
	}
	
	//TODO: correct returned value to see today's day in returned week
	public static function week_of_year($year, $month, $day) {
		$first_day_of_year = ((self::day_of_week($year, 1, 1)))%7;
		//print 'first_day_of_year: '.$first_day_of_year;
		$sum = $first_day_of_year;
		$week = 1;
		for($i = 1; $i < $month; $i++) {
			$sum += self::days_in_month($year, $i);
			$week++;
		}
		$sum += $day;
		//print $first_day_of_year.", ";
		//printf("%2.2f<br>", $sum/7);
		return ceil(($sum)/7);
	}
	public static function week_of_year_r(array $date) {
		return self::week_of_year($date['year'], $date['month'], $date['day']);
	}
	public static function day_of_year_r($date) {
		$sum = $date['day'];
		for($i = 1; $i < $date['month']; $i++)
			$sum += self::days_in_month($date['year'], $i);
		return $sum;
	}
	public static function begining_of_week_r($date) {
		if(isset($date['week']))
			return self::begining_of_week($date['year'], $date['week']);
		else
			return self::begining_of_week($date['year'], self::week_of_year_r($date));
	}
	
	public static function begining_of_week($year, $week) {
		$needed_day = 6 - (self::day_of_week($year, 1, 1));
		$needed_day = $needed_day + ($week-1)*7;
		$needed_day = -1*((self::day_of_week($year, 1, 1)))%7;
		$needed_day = $needed_day + ($week-1)*7 + 1;
		
		$r = 0;
		//print "_ $needed_day _ ";
		$i = 1;
		for($i = 1; $i <= 12; $i++) {
			if( ($r + self::days_in_month($year, $i)) >= $needed_day) {//self::day_of_year_r($date)) {
				//$i = 13;
				break;
			} else {
				$r += self::days_in_month($year, $i);
			}
		}
		$ret['year'] = $year;
		$ret['month'] = $i;
		$ret['day'] = $needed_day - $r;//$date['day'] - self::translate(self::day_of_week_r($date));//intval(self::day_of_year_r($date) - $r);
		if($ret['day'] <= 0) {
			$ret['day'] = 31 + $ret['day'];
			$ret['month'] = 12;
			$ret['year']--;
		}
		return $ret;
	}
	public static function ending_of_week($year, $week) {
		$needed_day = 6 - self::translate(self::day_of_week($year, 1, 1));
		$needed_day = $needed_day + ($week-1)*7;
		$needed_day = (7 - self::translate(self::day_of_week($year, 1, 1)))%7;
		$needed_day = $needed_day + ($week-1)*7 + 7;
		$r = 0;
		//print "_ $needed_day _ ";
		$i = 1;
		for($i = 1; $i <= 12; $i++) {
			if( ($r + self::days_in_month($year, $i)) > $needed_day) {//self::day_of_year_r($date)) {
				//$i = 13;
				break;
			} else {
				$r += self::days_in_month($year, $i);
			}
		}
		$ret['year'] = $year;
		$ret['month'] = $i;
		$ret['day'] = $needed_day - $r;//$date['day'] - self::translate(self::day_of_week_r($date));//intval(self::day_of_year_r($date) - $r);
		return $ret;
	}
	public static function day_of_week($Y, $M, $d, $mode = 0) {
		$idx = JDDayOfWeek ( cal_to_jd(CAL_GREGORIAN, $M, $d, $Y) , $mode );
		return CRM_Calendar_Utils_FuncCommon::translate($idx);
	}
	public static function day_of_week_r($date, $mode = 0) {
		$idx = JDDayOfWeek ( cal_to_jd(CAL_GREGORIAN, $date['month'], $date['day'], $date['year']) , $mode );
		return CRM_Calendar_Utils_FuncCommon::translate($idx);
	}
	public static function translate($day) {
		$a = $day - self::get_settings('first_day');
		if( $a < 0)
			$a = 7 + $a;
		return $a;
	}
	public static function translate_index($day) {
		return ($day+self::get_settings('first_day')) % 7;
	}
	public static function starting_day_r($date, $mode = 0) {
		return JDDayOfWeek ( cal_to_jd(CAL_GREGORIAN, $date['month'], 1, $date['year']) , $mode );
	}
	public static function days_in_month($year, $month) {
		if($month > -1)
			return cal_days_in_month(CAL_GREGORIAN, $month, $year);
		else
			return 31;
	}
	public static function days_in_month_r($date) {
		return cal_days_in_month(CAL_GREGORIAN, $date['month'], $date['year']);
	}

	
	public static function detailed_today() {
		//print "Welcome ".Base_User::get_user_login(self::logged)." (id: ".self::logged.")!<br>";
		//print "Today is ".self::day_of_week_r(self::today(), 1) . ", " . date("d M Y"); 
		$w = self::week_of_year_r(self::today());
		switch($w % 10) {
			case 1: $end = 'st'; break;
			case 2: $end = 'nd'; break;
			case 3: $end = 'rd'; break;
			default: $end = 'th';
		}
		if(10 < $w && $w < 13) $end = 'th';
		//print "<div ".Utils_ToolTipCommon::tip("Yes! This is what today is!").">".$w.$end." week of year, ";
		$w = self::day_of_year_r(self::today());
		switch($w % 10) {
			case 1: $end = 'st'; break;
			case 2: $end = 'nd'; break;
			case 3: $end = 'rd'; break;
			default: $end = 'th';
		}
		if(10 < ($w%100) && ($w%100) < 13) $end = 'th';
		print $w.$end." day of year.";//</div>";
		$re = self::begining_of_week(self::today(), self::week_of_year_r(self::today()));
		//print "This week begins on ".$re['month']. " ". $re['day'];
	}
}
?>
