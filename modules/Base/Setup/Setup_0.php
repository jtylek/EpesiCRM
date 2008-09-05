<?php
/**
 * Setup class
 *
 * This file contains setup module.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @license SPL
 * @package epesi-base-extra
 * @subpackage setup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides for administration of modules.
 */
class Base_Setup extends Module {

	public function admin() {
		$this->body();
	}

	public function body() {
		if($this->is_back() && $this->parent) {
			$this->parent->reset();
			return;
		}

		$post_install = & $this->get_module_variable('post-install');
		if(!is_array($post_install)) {
			//create default module form
			$form = & $this->init_module('Libs/QuickForm','Processing modules');

			//set defaults
			$form->setDefaults(array (
				'default_module' => Variable::get('default_module'),
				'simple' => Variable::get('simple_setup'),
				'anonymous_setup' => Variable::get('anonymous_setup')));

			$form->addElement('header', 'install_module_header', 'Modules Administration');
			//$form->addElement('checkbox','simple','Simple setup','',array('onChange'=>$form->get_submit_form_js(false)));
			$form->addElement('select','simple','Setup type',array(1=>'Simple',0=>'Advanced'),array('onChange'=>$form->get_submit_form_js(false)));
			$simple = $form->exportValue('simple');


			//install module header
			$form -> addElement('html','<tr><td colspan=2><br /><b>Please select modules to be installed/uninstalled.<br>For module details please click on "i" icon.</td></tr>');

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
					$func_simple = array($module_install_class,'simple_setup');
					$simple_module = is_callable($func_simple) && call_user_func($func_simple);
					if($simple && !$simple_module) continue;


					$func_info = array($module_install_class,'info');
					if(is_callable($func_info)) {
						$module_info = call_user_func($func_info);
						if($module_info) {
							$info = ' <a '.Libs_LeightboxCommon::get_open_href($entry).'><img style="vertical-align: middle; cursor: pointer;" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('Base_Setup', 'info.png').'></a>';
							$iii = '<h1>'.str_replace('_','/',$entry).'</h1><table>';
							foreach($module_info as $k=>$v)
								$iii .= '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
							$iii .= '</table>';
							Libs_LeightboxCommon::display($entry,$iii,'Additional info');
						} else $info = '';
					} else $info = '';

					$versions[-1]='not installed';
					ksort($versions);

					$path = explode('_',$entry);
					$c = & $structure;
					for($i=0, $path_count = count($path)-1;$i<$path_count;$i++){
						if(!array_key_exists($path[$i], $c)) {
							$c[$path[$i]] = array();
							$c[$path[$i]]['name'] = $path[$i];
							$c[$path[$i]]['sub'] = array();
						}
						$c = & $c[$path[$i]]['sub'];
					}
					$ele = $form->createElement('select', 'installed['.$entry.']', $path[count($path)-1], $versions);
					$ele->setValue($installed);
					$c[$path[count($path)-1]] = array();
					$c[$path[count($path)-1]]['name'] = '<table width=100%><tr><td width=100% align=left>' . $info . ' ' . $path[count($path)-1] . '</td><td align=right>' . $ele->toHtml() . '</td></tr></table>';
					$c[$path[count($path)-1]]['sub'] = array();
					array_push($def, array('installed['.$entry.']'=>$installed));


			}

			$tree = & $this->init_module('Utils/Tree');
			$tree->set_structure($structure);
			if ($simple) $tree->open_all();
			//$form->addElement('html', '<tr><td colspan=2>'.$tree->toHtml().'</td></tr>');
			$form->addElement('html', '<tr><td colspan=2>'.$this->get_html_of_module($tree).'</td></tr>');

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

			$form->setDefaults($def);

			//validation or display
			if ($form->exportValue('submited') && $form->validate()) {
				if($form->process(array (
					& $this,
					'validate'
				))) {
					Epesi::redirect();
				} else {
					print('<hr class="line"><center><a class="button"' . $this -> create_href(array()) . '>Back</a></center>');
				}
			} elseif(!$post_install){
				$form->display();
				Base_ActionBarCommon::add('scan','Scan for new modules',$this->create_confirm_callback_href('Parsing for additional modules may take up to several minutes, do you wish to continue?',array('Base_Setup','parse_modules_folder_refresh')));
				Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
				Base_ActionBarCommon::add('save', 'Save', $form->get_submit_form_href());
			}
		}

		if(is_array($post_install)) {
			foreach($post_install as $i=>$v) {
				ModuleManager::include_install($i);
				$f = array($i.'Install','post_install');
				$fs = array($i.'Install','post_install_process');
				if(!is_callable($f) || !is_callable($fs)) {
					unset($post_install[$i]);
					continue;
				}
				$ret = call_user_func($f);
				$form = $this->init_module('Libs/QuickForm',null,$i);
				$form->addElement('header',null,'Post installation of '.str_replace('_','/',$i));
				$form->add_array($ret);
				$form->addElement('submit',null,'OK');
				if($form->validate()) {
					$form->process($fs);
					unset($post_install[$i]);
				} else {
					$form->display();
					break;
				}
			}
			if(empty($post_install))
				Epesi::redirect();
		}

	}

	public static function parse_modules_folder_refresh(){
		Base_SetupCommon::refresh_available_modules();
		//location(array());
		return false;
	}

	public function validate($data) {

		$default_module = false;
		$simple = 0;
		$installed = array ();
		$install = array ();
		$uninstall = array();
		$anonymous_setup = false;
		$modified_modules_table = false;

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

		$post_install = array();
		//install
		foreach($install as $i=>$v) {
			$post_install[$i] = $v;
			if (!ModuleManager::install($i,$v))
				return false;
		}

		//uninstall
		$modules_prio_rev = array();
		foreach (ModuleManager::$modules as $k => $v)
			$modules_prio_rev[] = $k;
		$modules_prio_rev = array_reverse($modules_prio_rev);

		foreach ($modules_prio_rev as $k)
			if(array_key_exists($k, $uninstall)) {
				if (!ModuleManager::uninstall($k)) {
					return false;
				}
				if(count(ModuleManager::$modules)==0)
					print('No modules installed');
			}

		Base_ThemeCommon::create_cache();

		$this->set_module_variable('post-install',$post_install);
		return true;
	}
}
?>
