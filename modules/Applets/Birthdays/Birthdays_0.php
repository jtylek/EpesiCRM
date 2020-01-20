<?php
/**
 * @author j@epe.si
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.9.0
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

		$title = __('Birthdays upcoming in the next: %d days.', array($conf['no_of_days']));
		$sort_order = 'ASC';

		// initialize the recordset
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'contact','contact');

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
		if ($conf['no_of_days'] < 0) {
			$title = __('Birthdays from the last %d days.', array(-$conf['no_of_days']));
			$sort_order = 'DESC';
		}
		$dates = array();
		$now = strtotime(Base_RegionalSettingsCommon::time2reg(null, false, true, true, false));
		foreach (range(0, $conf['no_of_days']) as $day) {
			$dates[] = date('%%-%m-%d', strtotime("+$day days", $now));
		}
		$crits = array("~birth_date" => $dates);
		if ( (isset($conf['cont_type'])) && ($conf['cont_type']=='f') ) {
			$crits[':Fav'] = true;
		}

		// 3rd - sorting
		$sorting = array('birth_date'=> $sort_order);

		// 4th - function to return tooltip
		$tooltip = null;

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
		print($title);
		$this->display_module($rb, $conds, 'mini_view');
	}
}
?>