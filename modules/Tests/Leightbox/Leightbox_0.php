<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Leightbox extends Module{
	public function body(){
		print(Utils_PopupCalendarCommon::show('test','alert(__DAY__+\'.\'+__MONTH__+\'.\'+__YEAR__ )'));

		$form = $this->init_module('Libs/QuickForm', null, 'RBpicker_test');
		$form->addElement('multiselect', 'test', 'Test', array(1=>0, 4=>1, 7=>2, 8=>3));
		$form->addElement('submit', 'submit', 'Submit');
		$form->display();
		if ($form->validate()) print_r($form->exportValues());

		$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
		$this->display_module($rb1, array('contact' ,'test',array('Tests_LeightboxCommon','TEST'), array('company_name'=>1), array('country'=>true)));
		print($rb1->create_open_link('Click here!'));

		Libs_LeightboxCommon::display('leightbox1','<h1>Leightbox</h1>'.
							'ble ble ble','Test header');

		print('<hr><a '.Libs_LeightboxCommon::get_open_href('leightbox1').'>leightbox container</a>
			</div>');
		
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Leightbox/LeightboxInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Leightbox/Leightbox_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Leightbox/LeightboxCommon_0.php');
		
	}
}

?>
