<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Tooltip extends Module {
	
	public function body() {
		TestsCommon::heading(__('Tooltip'), __('point mouse here'));
		print(Utils_TooltipCommon::create('point mouse here', 'tip'));
		TestsCommon::source_card($this, 'modules/Tests/Tooltip/', array(
			'Install' => 'TooltipInstall.php',
			'Main' => 'Tooltip_0.php',
			'Common' => 'TooltipCommon_0.php',
		));
	}
}
?>



