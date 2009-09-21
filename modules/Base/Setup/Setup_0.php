<?php
/**
 * Setup class
 *
 * This file contains setup module.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
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
				if (ModuleManager::exists($row['name'])) {
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
            $is_required = ModuleManager::required_modules($simple ? false : true);
            if(!$simple) {
            // transform is_required array to javascript
                eval_js('var deps = new Array('.sizeof($is_required).');');
                foreach($is_required as $k => $mod) {
                    eval_js('deps["'.$k.'"] = new Array('.sizeof($mod).');');
                    $i = 0;
                    foreach($mod as $dep_mod) eval_js('deps["'.$k.'"]['.$i++.'] = "'.$dep_mod.'";');
                }
            // javascript to show warning only once and cascade uninstall
                eval_js('var showed = false;');
                eval_js_once('var original_select = new Array('.sizeof($is_required).');');
                eval_js_once('function show_alert(x, mod) {
                                if(x.options[x.selectedIndex].value == -2) {
                                    if(!showed) alert(\''.$this->t('Warning!\nAll data in reinstalled modules will be lost.').'\');
                                    showed=true;
                                    return;
                                }
                                if(x.selectedIndex != 0) {
                                    original_select[mod] = x.options[x.selectedIndex].value;
                                    return;
                                }
                                var str = "\n";
                                for(var i = 0; i < deps[mod].length; i++) {
                                    str+=" - " + deps[mod][i] + "\n";
                                }
                                if(confirm("'.$this->t('Warning! These modules will be deleted:').'" + str + "\n'.$this->t('Continue?').'") == false) {
                                    var ind = 0;
                                    for(; ind < x.options.length; ind++) if(x.options[ind].value == original_select[mod]) break;
                                    x.selectedIndex = ind;
                                    return;
                                }
                                for(var i = 0; i < deps[mod].length; i++) {
                                    document.getElementsByName("installed["+deps[mod][i]+"]")[0].selectedIndex=0;
                                }
                        }');
            }
			foreach($module_dirs as $entry=>$versions) {
					$installed = ModuleManager::is_installed($entry);

					$module_install_class = $entry.'Install';
					$func_simple = array($module_install_class,'simple_setup');
					$simple_module = is_callable($func_simple) && call_user_func($func_simple);
					if($simple && !$simple_module) continue;


					$func_info = array($module_install_class,'info');
                    $info = '';
					if(is_callable($func_info)) {
						$module_info = call_user_func($func_info);
						if($module_info) {
							$info = ' <a '.Libs_LeightboxCommon::get_open_href($entry).'><img style="vertical-align: middle; cursor: pointer;" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('Base_Setup', 'info.png').'></a>';
							$iii = '<h1>'.str_replace('_','/',$entry).'</h1><table>';
							foreach($module_info as $k=>$v)
								$iii .= '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
							$iii .= '</table>';
							Libs_LeightboxCommon::display($entry,$iii,'Additional info');
						}
					}

                    // Show Tooltip if module is required
                    $tooltip = null;
                    if(isset($is_required[$entry])) {
                        $tooltip = $this->t('This module cannot be removed.').'<br/>';
                        if($simple) {
                            $tooltip .= ($is_required[$entry]>1 ? $this->t('Required by %d modules.', array($is_required[$entry])) : $this->t('Required by %d module.', array($is_required[$entry])));
                        } else {
                            $tooltip .= $this->t('Required by:').'<ul>';
                            foreach($is_required[$entry] as $mod_name) {
                                $tooltip .= '<li>'.$mod_name.'</li>';
                            }
                            $tooltip .= '</ul>'.$this->t('Remove them first.');
                        }
                    }

					if(!isset($is_required[$entry]) || !$simple) $versions[-1]='not installed';
					ksort($versions);
                    if(!$simple && $installed!=-1 && !isset($is_required[$entry])) $versions[-2] = 'reinstall';

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
                    $params_arr = $simple ? array('style'=>'width: 100px') : array('onChange'=>"show_alert(this,'$entry');", 'style'=>'width: 100px');
   					$ele = $form->createElement('select', 'installed['.$entry.']', $path[count($path)-1], $versions, $params_arr);
       				$ele->setValue($installed);
                    if(!$simple) eval_js("original_select[\"$entry\"] = $installed");

					$c[$path[count($path)-1]] = array();
					$c[$path[count($path)-1]]['name'] = '<table width=100%><tr><td width=100% align=left>' . $info . ' ' . $path[count($path)-1] . '</td><td align=right '.($tooltip?Utils_TooltipCommon::open_tag_attrs($tooltip,false):'').'>' . $ele->toHtml() . '</td></tr></table>';
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
				ob_start();
				if (!$this->validate($form->getSubmitValues()))
					print('<hr class="line"><center><a class="button"' . $this -> create_href(array()) . '>Back</a></center>');
				if(is_array($post_install))
					ob_end_clean();
				else
					ob_end_flush();
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
            if($new_version==-2) {
                $uninstall[$name]=1;
                $install[$name]=$old_version;
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

		//install
		foreach($install as $i=>$v) {
			$post_install[$i] = $v;
			if (!ModuleManager::install($i,$v))
				return false;
		}
		$processed = ModuleManager::get_processed_modules();
		$this->set_module_variable('post-install',$processed['install']);

        Base_ThemeCommon::create_cache();

		if(empty($post_install))
			Epesi::redirect();

		return true;
	}
}
?>
