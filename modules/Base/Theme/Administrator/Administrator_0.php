<?php
/**
 * Theme_Administrator class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-base-extra
 * @subpackage theme-administrator
 */
class Base_Theme_Administrator extends Module implements Base_AdminInterface{
	
	public function body($arg) {
		$this->admin();
	}
	
	public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		
		$this->lang = $this->pack_module('Base/Lang'); 
		
		$form = $this->init_module('Libs/QuickForm','Changing theme');
		
		$themes = Base_Theme::list_themes();
		$form->addElement('select', 'theme', $this->lang->t('Choose theme'), $themes);
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), 'onClick="parent.location=\''.$this->create_back_href().'\'"');
		$form->addGroup(array($ok_b, $cancel_b));
		
		$form->setDefaults(array('theme'=>Variable::get('default_theme')));
		
		if($form->validate()) {
			$form->process(array(& $this, 'submit_admin'));
/*			if($this->parent->get_type()=='Base_Admin')
			    $this->parent->reset();
			else
			    location(array());*/
		} else
			$form->display();
	}
	
	public function submit_admin($data) {
		Variable::set('default_theme',$data['theme']);
		Base_StatusBarCommon::message('Theme changed - reloading page');
		print('<form id="base_theme_admin_refresh" method="post"><input type="hidden" value="1" name="refresh"></form>');
		eval_js('setTimeout(\'document.getElementById(\\\'base_theme_admin_refresh\\\').submit()\',\'4000\')');
//		eval_js('setTimeout(\'document.location=\\\'index.php?refresh=1\\\'\',\'4000\')');
		return true;
	}
	
}
?>
