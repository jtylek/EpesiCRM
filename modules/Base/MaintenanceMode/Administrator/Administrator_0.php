<?php
/**
 * MaintenanceMode class.
 * 
 * This class provides maintenance mode enable/disable admin interface.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides maintenance mode enable/disable admin interface.
 * @package epesi-base-extra
 * @subpackage maintenance-mode-administrator
 */
class Base_MaintenanceMode_Administrator extends Module implements Base_AdminInterface {
	
	public function body($arg) {
	}
	
	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}
		
		$lang = $this->pack_module('Base/Lang');
		
		$f = & $this->init_module('Libs/QuickForm');
		
		$f->addElement('select', 'm', $lang->t('Maintenance mode'), array(1=>$lang->ht('Yes'), 0=>$lang->ht('No')));
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $lang->ht('Cancel'), 'onClick="parent.location=\''.$this->create_back_href().'\'"');
		$f->addGroup(array($ok_b, $cancel_b));
		
		$f->setDefaults(array('m'=>((Base_MaintenanceModeCommon::get_mode())?'1':'0')));
		
		if($f->validate()) {
			$f->process(array(& $this, 'submit_admin'));
			$this->parent->reset();
		} else
			$f->display();	
	}
	
	public function submit_admin($data) {
		Base_MaintenanceModeCommon::set_mode(($data['m']=='1')?true:false);
	}
}
?>
