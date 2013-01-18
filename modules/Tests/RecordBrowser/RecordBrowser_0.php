<?php
/**
 * @author Olga Chlebus <ochlebus@telaxus.com>
 * @copyright Copyright &copy; 2013, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage record-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_RecordBrowser extends Module{
	public function body(){
		$rs = new Tests_RecordBrowser_Recordset();
        $this->rb = $rs->create_rb_module($this);
		$this->display_module($this->rb);
	}
}

?>
