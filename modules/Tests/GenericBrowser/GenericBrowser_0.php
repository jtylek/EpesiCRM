<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_GenericBrowser extends Module {
	
	public function body() {
 		TestsCommon::heading(__('Generic Browser'));

 		// A "simple table" is just a plain PHP array of rows - each row itself
 		// a plain array of cell values, one entry per column. Once the columns
 		// are declared, injecting it into GenericBrowser is a single foreach
 		// over add_row_array() - no per-cell add_row() call needed per row.
 		$table = array(
 			array('xxx', '123'),
 			array('sdasf', '567'),
 			array('wwww', 'abc'),
 			array('asgfs', 'bla bla'),
 			array('test', 'adsad'),
 			array('search', 'sjfksdfjdk'),
 			array('search keyword', 'test'),
 			array('ttttesst', 'djsdkdkdkd kskdk'),
 			array('xxx', 'yyyy'),
 		);
 		print '<h6>' . __('Generic Browser Table') . '</h6>';
 		$m = $this->init_module(Utils_GenericBrowser::module_name(),null,'t1');
 		$m->set_table_columns(array(
							  array('name'=>'xxx','width'=>50),
							  array('name'=>'xyz','width'=>50)));
 		foreach ($table as $row) $m->add_row_array($row);
 		$this->display_module($m);

 		// Same idea for a single searchable column - the table is just a
 		// column vector, one cell per row.
 		$searchable_table = array(
 			array('xxx'),
 			array('sdasf'),
 			array('wwww'),
 			array('asgfs'),
 			array('test'),
 			array('search'),
 			array('search keyword'),
 			array('ttttesst'),
 			array('xxxy'),
 		);
 		print '<h6 class="mt-4">' . __('GenericBrowser with Search') . '</h6>';
 		$m = $this->init_module(Utils_GenericBrowser::module_name(),null,'t2');
 		$m->set_table_columns(array(array('name'=>'xxx','search'=>1)));
 		foreach ($searchable_table as $row) $m->add_row_array($row);
 		$this->display_module($m,array(true),'automatic_display');

		TestsCommon::source_card($this, 'modules/Tests/GenericBrowser/', array(
			'Install' => 'GenericBrowserInstall.php',
			'Main' => 'GenericBrowser_0.php',
			'Common' => 'GenericBrowserCommon_0.php',
		));
	}
}
?>
