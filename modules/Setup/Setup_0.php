<?php
/**
 * Setup class
 * 
 * This file contains setup module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides for administration of modules.
 * @package tcms-base
 * @subpackage setup
 */
class Setup extends Module {

	public function body_access() {
		return (Variable::get('anonymous_setup') || Acl::check('Administration','Main'));
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
			'anonymous_setup' => Variable::get('anonymous_setup')));
//		print('='.Base_AclCommon::change_privileges('admin', array(Base_AclCommon::sa_group_id())).'=');
		

		//install module header
		$form->addElement('header', 'install_module_header', 'Module administration');

		//show uninstalled & installed modules
		$module_dirs = ModuleManager::list_modules();
		$subgroups = array();
		$structure = array();
		$def = array();
		foreach($module_dirs as $entry=>$versions) {
				$installed = ModuleManager::is_installed($entry);
				$versions[-1]='not installed';
				ksort($versions);
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
					if ($subgroups[$i] == $path[$i]) {
						$tab .= '*&nbsp;&nbsp;';
						continue;
					}
					$subgroups[$i] = $path[$i];
					//$form->addElement('static', 'group_header', '<div align=left>'.$tab.$path[$i].'</div>');
					$tab .= '*&nbsp;&nbsp;';
				}
				$subgroups[count($path)-1] = $path[count($path)-1];
					$ele = $form->createElement('select', 'installed['.$entry.']', $path[count($path)-1], $versions);
					$ele->setValue($installed);
					$c[$path[count($path)-1]] = array();
					$c[$path[count($path)-1]]['name'] = '<table width=100%><tr><td width=100% align=left>'.$path[count($path)-1].'</td><td align=right>' . $ele->toHtml() . '</td></tr></table>';
					
					//$c[$path[count($path)-1]]['name'] = $path[count($path)-1] ;
					$c[$path[count($path)-1]]['sub'] = array();
				array_push($def, array('installed['.$entry.']'=>$installed));
		
		
		}
				
		$tree = $this->init_module('Utils/Tree');
		
		$tree->set_structure($structure);
		//TODO: perhaps a tpl file so that no html insertion would be needed?
		$form->addElement('html', '</tr><tr><td colspan=2>'.$tree->toHtml().'</td></tr>');
		
		
		$form->addElement('header', 'anonymous_header', 'Other (dengerous, don\'t change if you are newbie)');
//		$form->addElement('header', 'anonymous_warning', 'If you don\'t have any authorization module installed don\'t turn it off!');
		$form->addElement('checkbox','anonymous_setup', 'Anonymous setup');

		//default module		
//		$form->addElement('header', 'default_warning', 'Only some modules can be default! If invalid module is set, you cannot');
		$av_modules=array();
		foreach($base->modules as $name=>$obj)
			$av_modules[$name] = $name;
		$form->addElement('select','default_module','Default module to display',$av_modules);
		
		//control buttons
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', 'OK');
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', 'Cancel', $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		
		$form->setDefaults($def);
		
		//validation or display
		if ($form->validate()) {
			if($form->process(array (
				& $this,
				'validate'
			))) {
				if($this->parent && $this->parent->get_type()=='Base_Admin') $this->parent->reset();
					else location(array());
			}
		} else $form->display();
	}

	public function validate($data) {
		global $base;
		
		$default_module = false;
		$installed = array ();
		$install = array ();
		$uninstall = array();
		$anonymous_setup = false;
		$modified_modules_table = false;
		$return_code = true;
		
		foreach ($data as $k => $v)
			${ $k } = $v;

		if ($default_module !== false)
			Variable::set('default_module', $default_module);

		Variable::set('anonymous_setup', $anonymous_setup);
				
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
		foreach ($base->modules as $k => $v)
			$modules_prio_rev[] = $k; 
		$modules_prio_rev = array_reverse($modules_prio_rev);
		
		foreach ($modules_prio_rev as $k) 
			if(array_key_exists($k, $uninstall)) {
			  	if($k=='Setup') {
					if(count($base->modules)==1) {
						$ret = SetupInstall::uninstall();
						if ($ret) {
//							session_destroy();
							print('No modules installed. Go <a href="http'.(($_SERVER['HTTPS'])?'s':'').'://'. $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']).'/">here</a> to install Setup module!');
							return false;
						} else
							print('Unable to remove Setup module!');
						return false;
					} else {
						print('You cannot delete setup if any other module is installed!');
						return false;
					}
				} else {
					if (!ModuleManager::uninstall($k)) {
						$return_code = false;
						break;
					}
			  	}
			}
		
		return $return_code;
	}
}
?>
