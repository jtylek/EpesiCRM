<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage shareduniquehref
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHref extends Module {
	
	public function body() {
		print('<a '.$this->create_unique_href(array('test'=>'ble'),'Ble Ble Ble').'>Click here</a><br>');
		$m = & $this->init_module('Tests/SharedUniqueHref/a');
		$this->share_unique_href_variable('test',$m);
		$this->display_module($m);
	}
}
?>


