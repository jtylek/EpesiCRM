<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-tests
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Calendar extends Module {

	public function body() {
		$c = $this->init_module(Utils_Calendar::module_name(),array('Tests/Calendar/Event',array('default_view'=>'month','first_day_of_week'=>1)));
		$this->display_module($c);
	}

}

?>