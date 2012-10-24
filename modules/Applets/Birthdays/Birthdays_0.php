<?php
/**
 * @author jtylek@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage birthdays
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_Birthdays extends Module {
	private $date;

	public function body() {
	}

public function applet($conf, & $opts) {
		//available applet options: toggle,href,title,go,go_function,go_arguments,go_contruct_arguments
		$opts['go'] = false; // enable/disable full screen
		$opts['title'] = $conf['title'];

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
							array('field'=>'last_name', 'width'=>15),
							array('field'=>'first_name', 'width'=>15),
							array('field'=>'birth_date', 'width'=>15)
						);
		// 2nd - criteria (filter)
		// TO DO - filter date - today through today+2 weeks
		$dates = array();
		for ($i=0;$i<$conf['no_of_days'];$i++)
			$dates[] = DB::Concat(DB::qstr('%'),DB::qstr(date('-m-d',strtotime(Base_RegionalSettingsCommon::time2reg(strtotime('+'.$i.' days'), false, true, true, false)))));
		if ( (isset($conf['cont_type'])) && ($conf['cont_type']=='f') ) {
				$crits=array(':Fav'=>true,'"~birth_date'=>$dates);
			} else {
				$crits=array('"~birth_date'=>$dates);
		}

		// 3rd - sorting
		$sorting = array('last_name'=>'ASC');

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
		print(__('Birthdays upcoming in the next: %d days.', array($conf['no_of_days'])));
		$this->display_module($rb, $conds, 'mini_view');
	}
}
?>