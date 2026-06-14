<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_Calendar_Event extends Module {
	abstract public function add($def_date,$timeless=false);
	abstract public function view($id);
	abstract public function edit($id);
	
	public function back_to_calendar() {
		Base_BoxCommon::pop_main();
	}
}
?>
