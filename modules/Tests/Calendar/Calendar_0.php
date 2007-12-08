<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package tests-codepress
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Calendar extends Module {

	public function body() {
		$c = $this->init_module('Utils/Calendar',array(array('default_view'=>'day')));
		$this->display_module($c);
	}

}

?>