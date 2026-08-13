<?php
/**
 * Epesi developer editor
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-develop
 * @subpackage moduleeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_ModuleEditor extends Module {

	public function body() {
		$d = $this->get_module_variable('dir','.');
		$hidden = $this->get_module_variable('hidden',false);
		$d_writable = is_writable($d);
		
		if($d_writable) {
			$exec = ' href="javascript:void(0)" onClick="var pv=prompt(\''.Epesi::escapeJS(__('Folder name')).'\');if(pv){_chj(\'new_folder_prompt_value=\'+pv,\'Creating directory\');}"';
			if(isset($_REQUEST['new_folder_prompt_value']))
				mkdir($d.'/'.$_REQUEST['new_folder_prompt_value']);
			Base_ActionBarCommon::add('add',__('New folder'),$exec);

			$exec = ' href="javascript:void(0)" onClick="var pv=prompt(\''.Epesi::escapeJS(__('Filename')).'\');if(pv){_chj(\'new_file_prompt_value=\'+pv,\'Creating file\');}"';
			if(isset($_REQUEST['new_file_prompt_value']))
				file_put_contents($d.'/'.$_REQUEST['new_file_prompt_value'],'');
			Base_ActionBarCommon::add('add',__('New file'),$exec);

//			Base_ActionBarCommon::add('save',__('Upload file'),$this->create_callback_href(array($this,'upload_file')));
		}
		Base_ActionBarCommon::add('folder',__('Toggle hidden'),$this->create_callback_href(array($this,'toggle_hidden')));

		$files = scandir($d);
		if($d==='.') $files = array_diff($files,array('..'));
		$gb = $this->init_module('Utils/GenericBrowser',null,'browser');
 		$gb->set_table_columns(array(
				array('name'=>'Filename'),
				array('name'=>'Readable'),
				array('name'=>'Writable'),
				array('name'=>'Modification time'),
			));
		foreach($files as $f) {
			if($f=='.') continue;
			if($f!='..' && !$hidden && preg_match('/(^\.)|(~$)/',$f)) continue;
			
			$path = ($f=='..')?dirname($d):$d.'/'.$f;
			$readable = is_readable($path);
			$writable = is_writable($path);

			if(is_dir($path) && $readable)
				$label = '<a '.$this->create_callback_href(array($this,'open_dir'),$path).'>'.$f.'</a>';
			else
				$label = $f;

			$row = $gb->get_new_row();
			$row->add_data($label,$readable?'<span style="color:green;">'.__('Yes').'</span>':'<span style="color:red;font-weight:bold;">'.__('No').'</span>',
				$writable?'<span style="color:green;">'.__('Yes').'</span>':'<span style="color:red;font-weight:bold;">'.__('No').'</span>',date("d F Y H:i:s", filemtime($path)));

			if(preg_match('/\.(php|js|tpl|css|html|htm|sql|text|txt)$/i',$f)) {
				$row->add_action($this->create_callback_href(array($this,'view_file'),array($path,false)),'View');
				if($writable)
					$row->add_action($this->create_callback_href(array($this,'view_file'),array($path,true)),'Edit');
			} elseif(preg_match('/\.(gif|jpg|jpeg|png)$/i',$f)) {
				$row->add_action('href="'.$path.'" target="_blank"','View');				
			}
			if($f!='..' && $d_writable)
				$row->add_action($this->create_confirm_callback_href(__('Delete this file?'),array($this,'delete_file'),$path),'Delete');
		}
		
		$this->display_module($gb);
		
	}
	
	public function delete_file($f) {
		recursive_rmdir($f);
	}
	
	public function open_dir($f) {
		$this->set_module_variable('dir',$f);
	}

	public function toggle_hidden() {
		$x = & $this->get_module_variable('hidden');
		$x = !$x;
	}
	
	public function upload_file() {
		
	}
	
	public function view_file($file, $edit=false) {
		if($this->is_back()) return false;
		
		$cont = @file_get_contents($file);
		if($cont===false) {
			Epesi::alert(__('Invalid file'));
			return false;
		}
		$writable = is_writable($file);

		$f = $this->init_module('Libs/QuickForm','codepress');
		
		if(!$writable || !$edit)
			$f->addElement('header',null,__('View file %s',array($file)));
		else
			$f->addElement('header',null,__('Edit file %s',array($file)));

		$cp = $f->addElement('codepress','cp','');

		$cp->setRows(15);
		$cp->setCols(100);
		$lang = substr(strrchr(basename($file),'.'),1);
		if(strcasecmp($lang,'js')==0) $lang = 'javascript';
		elseif(strcasecmp($lang,'tpl')==0 || strcasecmp($lang,'htm')==0) $lang = 'html';
		elseif(strcasecmp($lang,'txt')==0) $lang = 'text';
		else $lang = strtolower($lang);
		$cp->setLang($lang);

		$f->setDefaults(array('cp'=>$cont));
		if($f->validate()) {
			file_put_contents($file,$f->exportValue('cp'));
			Base_StatusBarCommon::message('File saved');
			return false;
		}
		
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		if($writable && $edit)
			Base_ActionBarCommon::add('save',__('Save'),' href="javascript:void(0)" onClick="'.$f->get_submit_form_js().'"');
		else
			$f->freeze();

		$f->display();
		
		return true;
	}

}

?>