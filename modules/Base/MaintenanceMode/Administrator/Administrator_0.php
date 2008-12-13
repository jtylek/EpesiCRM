<?php
/**
 * MaintenanceMode class.
 * 
 * This class provides maintenance mode enable/disable admin interface.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage maintenancemode-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MaintenanceMode_Administrator extends Module implements Base_AdminInterface {
	
	public function body() {
	}
	
	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}
		
		$f = & $this->init_module('Libs/QuickForm');
		
		$f->addElement('header', 'module_header', 'Maintenance Mode Administration');
		$f->addElement('select', 'm', $this->t('Maintenance mode'), array(1=>$this->ht('Yes'), 0=>$this->ht('No')));
		
		/*
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->ht('Cancel'), 'onClick="parent.location=\''.$this->create_back_href().'\'"');
		$f->addGroup(array($ok_b, $cancel_b));
		*/
		
		$f->setDefaults(array('m'=>((Base_MaintenanceModeCommon::get_mode())?'1':'0')));
		
		if($f->validate()) {
			$f->process(array(& $this, 'submit_admin'));
			$this->parent->reset();
		} else
			$f->display();
		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
		Base_ActionBarCommon::add('save', 'Save', $f->get_submit_form_href());
	}
	
	public function submit_admin($data) {
		Base_MaintenanceModeCommon::set_mode(($data['m']=='1')?true:false);
	}
}
?>
