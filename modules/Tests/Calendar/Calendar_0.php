<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Calendar extends Module {

	public function body() {
		$c = $this->init_module('Utils/Calendar',array('Tests/Calendar/Event',array('default_view'=>'month','first_day_of_week'=>1)));
		$this->display_module($c);
	}

}

?>