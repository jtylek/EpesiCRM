<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage shareduniquehref
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHref_a extends Module {
	
	public function body() {
		print '<p class="mt-2 mb-0">Submodule received: <strong>'.htmlspecialchars((string)$this->get_unique_href_variable('test')).'</strong></p>';
	}
}
?>


