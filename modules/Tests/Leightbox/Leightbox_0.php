<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage lightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Leightbox extends Module{
	public function body(){
		TestsCommon::heading(__('Leightbox'));
		$form = $this->init_module(Libs_QuickForm::module_name(), null, 'RBpicker_test');
		$form->addElement('multiselect', 'test', 'Test', array(1=>0, 4=>1, 7=>2, 8=>3));
		$form->addElement('submit', 'submit', 'Submit');
		$form->display();
		if ($form->validate()) print '<pre>'.htmlspecialchars(print_r($form->exportValues(), true)).'</pre>';

		$rb1 = $this->init_module(Utils_RecordBrowser_RecordPicker::module_name());
		$this->display_module($rb1, array('contact' ,'test',array('Tests_LeightboxCommon','TEST'), array('company_name'=>1), array('country'=>true)));
		print '<p>'.$rb1->create_open_link('Click here!').'</p>';

		Libs_LeightboxCommon::display('leightbox1','<h1>Leightbox</h1>'.
							'ble ble ble','Test header');

		print('<p><a class="btn btn-outline-primary btn-sm" '.Libs_LeightboxCommon::get_open_href('leightbox1').'>'.__('Open leightbox container').'</a></p>');

		TestsCommon::source_card($this, 'modules/Tests/Leightbox/', array(
			'Install' => 'LeightboxInstall.php',
			'Main' => 'Leightbox_0.php',
			'Common' => 'LeightboxCommon_0.php',
		));
	}
}

?>
