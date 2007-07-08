<?php
/**
 * Setup class
 * 
 * This file contains setup module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides for administration of modules.
 * @package epesi-base
 * @subpackage setup
 */
class Base_Setup extends Module {

	public function admin() {
		$this->body();
	}

	public function body($arg) {
		global $base;

		if($this->is_back() && $this->parent) {
			$this->parent->reset();
			return;
		}

		//create default module form
		$form = & $this->init_module('Libs/QuickForm','Processing modules');
		
		//set defaults
		$form->setDefaults(array (
			'default_module' => Variable::get('default_module'), 
			'simple' => Variable::get('simple_setup'),
			'anonymous_setup' => Variable::get('anonymous_setup')));
//		print('='.Base_AclCommon::change_privileges('admin', array(Base_AclCommon::sa_group_id())).'=');
		
		$form->addElement('header', 'install_module_header', 'Module administration');
		//$form->addElement('checkbox','simple','Simple setup','',array('onChange'=>$form->get_submit_form_js(false)));
		$form->addElement('select','simple','Setup type',array(1=>'Simple',0=>'Advanced'),array('onChange'=>$form->get_submit_form_js(false)));
		$simple = $form->exportValue('simple');


		//install module header
		$form->addElement('header', 'install_module_info', 'Please select modules to be installed/uninstalled.<br>For module details please click on "info"');

		//show uninstalled & installed modules
		$ret = DB::Execute('SELECT * FROM available_modules');
		$module_dirs = array();
		while ($row = $ret->FetchRow()) {
			if (ModuleManager::exists($row['name'],$row['vkey'])) {
				$module_dirs[$row['name']][$row['vkey']] = $row['version'];
				ModuleManager::include_install($row['name']);
			} else {
				DB::Execute('DELETE FROM available_modules WHERE name=%s and vkey=%d',array($row['name'],$row['vkey']));	
			}
		}
		if (empty($module_dirs))
			$module_dirs = Base_SetupCommon::refresh_available_modules();
			
		$subgroups = array();
		$structure = array();
		$def = array();
		foreach($module_dirs as $entry=>$versions) {
				$installed = ModuleManager::is_installed($entry);

				$module_install_class = $entry.'Install';
				$simple_module = call_user_func(array($module_install_class,'simple_setup'));
				if($simple && !$simple_module) continue;


				$module_info = call_user_func(array($module_install_class,'info'));
				if($module_info) {
					$info = ' (<a rel="'.$entry.'" class="lbOn">info</a>)';
					$iii = '<div id="'.$entry.'" class="leightbox"><h1>'.str_replace('_','/',$entry).'</h1><table>';
					foreach($module_info as $k=>$v)
						$iii .= '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
					$iii .= '</table><a class="lbAction" rel="deactivate">Close</a></div>';
					print($iii);
				} else $info = '';

				$versions[-1]='not installed';
				ksort($versions);

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
				$ele = $form->createElement('select', 'installed['.$entry.']', $path[count($path)-1], $versions);
				$ele->setValue($installed);
				$c[$path[count($path)-1]] = array();
				$c[$path[count($path)-1]]['name'] = '<table width=100%><tr><td width=100% align=left>'.$path[count($path)-1].$info.'</td><td align=right>' . $ele->toHtml() . '</td></tr></table>';
				$c[$path[count($path)-1]]['sub'] = array();
				array_push($def, array('installed['.$entry.']'=>$installed));
		
		
		}

		$tree = & $this->init_module('Utils/Tree');
		$tree->set_structure($structure);
		if ($simple) $tree->open_all();
		$form->addElement('html', '<tr><td colspan=2>'.$tree->toHtml().'</td></tr>');
		
		if(!$simple) {
			$form->addElement('header', 'anonymous_header', 'Other (dangerous, don\'t change if you are newbie)');
			$form->addElement('checkbox','anonymous_setup', 'Anonymous setup');

		//default module		
			$av_modules=array();
			foreach(ModuleManager::$modules as $name=>$obj)
				$av_modules[$name] = $name;
			$form->addElement('select','default_module','Default module to display',$av_modules);
		}
				
		//print $tree->toHtml();
		//control buttons
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', 'OK');
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', 'Cancel', $this->create_back_href());
		$parse_b = HTML_QuickForm::createElement('button', 'parse_button', 'Check for available modules', $this->create_confirm_callback_href('Parsing for additional modules may take up to several minutes, do you wish to continue?',array('Base_Setup','parse_modules_folder_refresh')));
		$form->addGroup(array($parse_b,$ok_b, $cancel_b));
		
		$form->setDefaults($def);
		
		//validation or display
		if ($form->exportValue('submited') && $form->validate()) {
			if($form->process(array (
				& $this,
				'validate'
			))) {
				if($this->parent && $this->parent->get_type()=='Base_Admin') $this->parent->reset();
					else location(array());
			}
		} else $form->display();
	}
	
	public static function parse_modules_folder_refresh(){
		Base_SetupCommon::refresh_available_modules();
		//location(array());
		return false;
	}
	
	public function validate($data) {
		global $base;
		
		$default_module = false;
		$simple = 0;
		$installed = array ();
		$install = array ();
		$uninstall = array();
		$anonymous_setup = false;
		$modified_modules_table = false;
		$return_code = true;
		
		foreach ($data as $k => $v)
			${ $k } = $v;

		if (!$simple) {
			if($default_module!==false)
				Variable::set('default_module', $default_module);
			Variable::set('anonymous_setup', $anonymous_setup);
		}
		Variable::set('simple_setup', $simple);
				
		foreach ($installed as $name => $new_version) {
			$old_version = ModuleManager::is_installed($name);
			if($old_version==$new_version) continue;
			if($old_version==-1 && $new_version>=0) {
				$install[$name]=$new_version;
				continue;
			}
			if($old_version>=0 && $new_version==-1) {
				$uninstall[$name]=1;
				continue;
			}
			if($old_version<$new_version) {
				if(!ModuleManager::upgrade($name, $new_version))
					return false;
				continue;
			}
			if($old_version>$new_version) {
				if(!ModuleManager::downgrade($name, $new_version))
					return false;
				continue;
			}
		}
		
		//install
		foreach($install as $i=>$v)
			if (!ModuleManager::install($i,$v))
				return false;
				
		
		//uninstall
		$modules_prio_rev = array();
		foreach (ModuleManager::$modules as $k => $v)
			$modules_prio_rev[] = $k; 
		$modules_prio_rev = array_reverse($modules_prio_rev);
		
		foreach ($modules_prio_rev as $k) 
			if(array_key_exists($k, $uninstall)) {
				if (!ModuleManager::uninstall($k)) {
					$return_code = false;
					break;
				}
				if(count(ModuleManager::$modules)==0)
					print('No modules installed');
			}
		
		return $return_code;
	}
}
?>
