<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage shareduniquehref
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHref extends Module {
	
	public function body() {
		TestsCommon::heading(__('Shared Unique Href'));
		print '<p><a class="btn btn-outline-primary btn-sm" '.$this->create_unique_href(array('test'=>'ble'),'Ble Ble Ble').'>Click here</a></p>';
		$m = $this->init_module('Tests/SharedUniqueHref/a');
		$this->share_unique_href_variable('test',$m);
		$this->display_module($m);

		TestsCommon::source_card($this, 'modules/Tests/SharedUniqueHref/', array(
			'Install' => 'SharedUniqueHrefInstall.php',
			'Main' => 'SharedUniqueHref_0.php',
			'Common' => 'SharedUniqueHrefCommon_0.php',
		));
	}
}
?>


