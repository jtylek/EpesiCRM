<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2022 Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage shareduniquehref
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHref_a extends Module {
	
	public function body() {
		print('Submodule received: '.$this->get_unique_href_variable('test'));
	}
}
?>


