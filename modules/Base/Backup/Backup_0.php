<?php
/**
 * Backup class.
 * 
 * This class provides functions for administrating the backup files.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage backup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Backup extends Module {
	
	public function body() {
	} 
	
	public function admin() {
		$theme = & $this->pack_module('Base/Theme');
		
		$theme->assign('available_backups',$this->t('Available backups'));
		$gb = & $this->init_module('Utils/GenericBrowser',null,'backup');
		$gb->set_table_columns(array(
			array('name'=>$this->t('Name')), 
			array('name'=>$this->t('Version')), 
			array('name'=>$this->t('Date'))));
		$backups_list = ModuleManager::list_backups();
		$backups = array();
		foreach($backups_list as $b) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_action($this->create_callback_href(array('Base_Backup','delete_backup'), array($b)),'Delete');
			if($b['version']==ModuleManager::is_installed($b['name'])) { 
				$gb_row->add_action($this->create_callback_href(array('Base_Backup','restore_backup'), array($b, true)),'Restore');
				$gb_row->add_action($this->create_callback_href(array('Base_Backup','restore_backup'), array($b, false)),'Append data');
			}
			$gb_row->add_data($b['name'], $b['version'], date("r",$b['date']));
		}
		$theme->assign('backups_table',$this->get_html_of_module($gb));
		
		$theme->assign('create_backup',$this->t('Create backup'));
		$form = & $this->init_module('Libs/QuickForm');
		$mods = array();
		foreach(ModuleManager::$modules as $m=>$v) {
			ModuleManager::include_install($m);
			if (!is_callable(array($m.'Install','backup'))) continue;
			$mods[] = $m;
		}
		asort($mods);
			
		$structure = array();
		foreach($mods as $entry) {
			$tab = '';
			$path = explode('_',$entry);
			$c = & $structure;
			for($i=0;$i<count($path)-1;$i++){
				if(!key_exists($path[$i], $c)) {
					$c[$path[$i]] = array();
					$c[$path[$i]]['name'] = $path[$i];
					$c[$path[$i]]['sub'] = array();
				}
				$c = & $c[$path[$i]]['sub'];
			}
			$ele = $form->createElement('checkbox', 'backup['.$entry.']', $path[count($path)-1]);
			$c[$path[count($path)-1]] = array();
			$c[$path[count($path)-1]]['name'] = '<table width=100%><tr><td width=100% align=left>'.$path[count($path)-1].'</td><td align=right>' . $ele->toHtml() . '</td></tr></table>';
			$c[$path[count($path)-1]]['sub'] = array();
		}

		$tree = & $this->init_module('Utils/Tree');		
		$tree->set_structure($structure);
		//$tree->set_inline_display(true);
		$theme->assign('tree',$this->get_html_of_module($tree));
		
		$form->addElement('submit', 'create_backup', $this->ht('Create backup'));
		if($form->validate()) {
			if($form->process(array($this,'submit_backup')))
				location(array());
		}
		$form->assign_theme('form',$theme);
		$theme->display();
	}
	
	public function submit_backup($data) {
		if(!isset($data['backup'])) return true;
		$bacs = $data['backup'];
		$ret = true;
		foreach($bacs as $k=>$v) {
			if(!ModuleManager::backup($k)) 
				$ret = false;
		}
		return $ret;
	}
	
	public static function delete_backup($b) {
		recursive_rmdir('backup/'.$b['name'].'__'.$b['version'].'__'.$b['date']);
		location(array());
	}
	
	public static function restore_backup($b, $delete_data) {
		ModuleManager::restore($b['name'], $b['date'], $delete_data);
	}
}

?>