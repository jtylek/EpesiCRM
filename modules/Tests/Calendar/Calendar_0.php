<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Calendar extends Module {

	public function body() {
		$c = $this->init_module(Utils_Calendar::module_name(),array('Tests/Calendar/Event',array('default_view'=>'month','first_day_of_week'=>1)));
		$this->display_module($c);
		TestsCommon::source_card($this, 'modules/Tests/Calendar/', array(
			'Install' => 'CalendarInstall.php',
			'Main' => 'Calendar_0.php',
			'Common' => 'CalendarCommon_0.php',
		));
	}

}

?>