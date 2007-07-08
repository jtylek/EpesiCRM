<?php
/**
 * QuickAccess class.
 * 
 * This class provides functionality for QuickAccess class. 
 * 
 * @author Arkadiusz Bisaga <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * QuickAccess class.
 * @package epesi-base-extra
 * @subpackage menu-quick-access
 */
class Base_Menu_QuickAccess extends Module {
	private $lang;
	
	public function body() {
		global $base;
		$this->lang = & $this->pack_module('Base/Lang');
		
		$form = & $this->init_module('Libs/QuickForm',$this->lang->ht('Saving settings'),'quick_access');
		
		if (!Base_AclCommon::i_am_user()) {
			print($this->lang->t('First log in to the system.'));
			return;
		}

		$modules_menu = array();
		$tools_menu = array();
		foreach(ModuleManager::$modules as $name=>$obj) {
			if ($name=='Base_Admin') continue;
			if ($name=='Base_Menu_QuickAccess') continue;
			if(method_exists($obj['name'].'Common', 'menu')) {
				$module_menu = call_user_func(array($obj['name'].'Common','menu'));
				if(!is_array($module_menu)) continue;
				Base_Menu::add_default_menu($module_menu, $name);
				$modules_menu = array_merge_recursive($modules_menu,$module_menu);
			}
			if(method_exists($obj['name'].'Common', 'tool_menu')) {
				$module_menu = call_user_func(array($obj['name'].'Common','tool_menu'));
				if(!is_array($module_menu)) continue;
				Base_Menu::add_default_menu($module_menu, $name);
				$tools_menu = array_merge_recursive($tools_menu,$module_menu);
			}
		}
		$tools_menu = array('Tools'=>array_merge($tools_menu,array('__submenu__'=>1)));
		$menu = array_merge($modules_menu,$tools_menu);
		ksort($menu);

		$form->addElement('header', 'select_quick_access_links', $this->lang->t('Select your Quick Access menu elements'));

		$defaults = array();
		self::check_for_links($form,'',$menu,$defaults);
		$form -> setDefaults($defaults);

		$form->addElement('submit', 'submit_button',$this->lang->ht('OK'));
		
		if ($form->validate()){
			$form->process(array(& $this, 'submit_settings'));
			location(array('box_main_module'=>'Base_User_Settings'));
		} else
			$form->display();
	}
	
	public function submit_settings($values){
		DB::Execute('DELETE FROM quick_access WHERE user_login_id = %d',Base_UserCommon::get_my_user_id());
		foreach($values as $k=>$v){
			if ($k=='submited' || $k=='submit_button' || $v!=1) continue;
			$info = explode('#qa_sep#',$k);
			DB::Execute('INSERT INTO quick_access VALUES (%d, %s, %s)',array(Base_UserCommon::get_my_user_id(),str_replace('_',' ',$info[1]),$info[0]));
		}
		Base_StatusBarCommon::message($this->lang->t('Setting saved'));
		return true;
	}
	
	private function check_for_links(& $form,$prefix,$array,& $defaults){
		foreach($array as $k=>$v){
			if (substr($k,0,2)=='__') continue;
			if (array_key_exists('__submenu__',$v)) $this->check_for_links($form,$prefix.$k.': ',$v,$defaults);
			else {
				$http_query = http_build_query($v,'','&');
				$form -> addElement('checkbox',$http_query.'#qa_sep#'.str_replace(' ','_',$prefix.$k),'<div align=left>'.$this->lang->t($prefix.$k).'</div>');
				$ret = DB::Execute('SELECT * FROM quick_access WHERE user_login_id = %d AND label = %s',array(Base_UserCommon::get_my_user_id(),$prefix.$k))->FetchRow();
				if ($ret) $defaults[$http_query.'#qa_sep#'.str_replace(' ','_',$prefix.$k)] = 1; 
			}
		}
	}
	
}
?>
