<?php
/**
 * Backup class.
 * 
 * This class provides functions for administrating the backup files.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides functions for administrating the backup files.
 * @package tcms-base-extra
 * @subpackage backup
 */
class Base_Backup extends Module {
	
	public function body($arg) {
	} 
	
	public function admin() {
		global $base;
		$this->lang = & $this->pack_module('Base/Lang');
		
		print('<h1>'.$this->lang->t('Available backups').'</h1>');
		$gb = $this->init_module('Utils/GenericBrowser');
		$gb->set_table_columns(array($this->lang->t('Name'), $this->lang->t('Version'), $this->lang->t('Date'), $this->lang->t('Actions')));
		$backups_list = ModuleManager::list_backups();
		$backups = array();
		foreach($backups_list as $b) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_action($this->create_callback_href(array('Base_Backup','delete_backup'), $b),'Delete');
			if($b['version']==ModuleManager::is_installed($b['name'])) { 
				$gb_row->add_action($this->create_callback_href(array('Base_Backup','restore_backup'), array($b, true)),'Restore');
				$gb_row->add_action($this->create_callback_href(array('Base_Backup','restore_backup'), array($b, false)),'Append data');
			}
			$gb_row->add_data($b['name'], $b['version'], date("r",$b['date']));
		}
		$this->display_module($gb);
		
		
		
		print('<h1>'.$this->lang->t('Create backup').'</h1>');
		$form = & $this->init_module('Libs/QuickForm');
		$mods = array();
		foreach($base->modules as $m=>$v) {
			if ($v['name']!=$m || !is_callable(array($m.'Init','backup'))) continue;
			$mods[] = $m;
		}
		asort($mods);
			
		$subgroups = array();
		foreach($mods as $entry) {
			$tab = '';
			$path = explode('_',$entry);
			for($i=0;$i<count($path)-1;$i++){
				if ($subgroups[$i] == $path[$i]) {
					$tab .= '*&nbsp;&nbsp;';
					continue;
				}
				$subgroups[$i] = $path[$i];
				$form->addElement('static', 'group_header', '<div align=left>'.$tab.$path[$i].'</div>');
				$tab .= '*&nbsp;&nbsp;';
			}
			$subgroups[count($path)-1] = $path[count($path)-1];
			$form->addElement('checkbox', 'backup['.$entry.']', '<div align=left>'.$tab.$path[count($path)-1].'</div>');
		}
		
		$form->addElement('submit', 'submit_button', $this->lang->ht('Create backup'));
		
		if($form->validate()) {
			if($form->process(array($this,'submit_backup')))
				location(array());
		} else
			$form->display();
	}
	
	public function submit_backup($data) {
		$bacs = $data['backup'];
		$ret = true;
		foreach($bacs as $k=>$v) {
			if(!ModuleManager::backup($k)) 
				$ret = false;
		}
		return $ret;
	}
	
	public static function delete_backup($b) {
		recursive_rmdir('backup/'.$b['name'].'-'.$b['version'].'-'.$b['date']);
		location(array());
	}
	
	public static function restore_backup($b, $delete_data) {
		ModuleManager::restore($b['name'], $b['date'], $delete_data);
	}
}

?>