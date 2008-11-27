<?php
/**
 * 
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-birthdays
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Birthdays extends Module {
	private $date;
	private $lang;

	public function body() {
	}

public function applet($conf,$opts) {
		$opts['go'] = false; // enable/disable full screen

		// initialize the recordset
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact');
		$me = CRM_ContactsCommon::get_my_record();

		// $conds - parameters for the applet
		// 1st - table field names, width, truncate
		// 2nd - criteria (filter)
		// 3rd - sorting
		// 4th - function to return tooltip
		// 5th - limit how many records are returned, null = no limit
		// 6th - Actions icons - default are view + info (with tooltip)
		
		// 1st - table field names
		$cols = array(
							array('field'=>'last_name', 'width'=>15, 'cut'=>18),
							array('field'=>'first_name', 'width'=>15, 'cut'=>18),
							array('field'=>'birth_date', 'width'=>15, 'cut'=>18)
						);
		// 2nd - criteria (filter)
		// TO DO - filter date - today through today+2 weeks
		$dates = array();
		for ($i=0;$i<7;$i++)
			$dates[] = DB::Concat(DB::qstr('%'),DB::qstr(date('m-d',strtotime(Base_RegionalSettingsCommon::time2reg(strtotime('+'.$i.' days'),false)))),DB::qstr('%')); 
		$crits=array(':Fav'=>true,'"~birth_date'=>$dates);

		// 3rd - sorting
		$sorting = array('birth_date'=>'ASC');

		// 4th - function to return tooltip
		$tooltip = 'test';

		// 5th - limit how many records are returned, null = no limit
		$limit = null;

		// 6th - Actions icons - default are view + info (with tooltip)

		$conds = array(
									$cols,
									$crits,
									$sorting,
									$tooltip,
									$limit,
									$conf,
									& $opts
				);
		// initialize miniview
		$this->display_module($rb, $conds, 'mini_view');
	}
}
?>