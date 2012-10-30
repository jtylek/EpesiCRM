<?php
/**
 * User_Settings class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user-settings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_Settings extends Module {
    private $settings_fields;
    private $set_default_js;
    private static $sep = "__";
    private $indicator = '';

    public function admin() {
        $this->body(null,true);
    }

    public function body($branch=null,$admin_settings=false) {
        $branch = $this->get_module_variable_or_unique_href_variable('settings_branch',$branch);
        if($branch!==null && $this->is_back()) {
            $branch = null;
        }
		if ($branch===null) {
			if($this->is_back()) {
				if($this->parent->get_type()=='Base_Admin')
					$this->parent->reset();
				else
					location(array());
				return;
			}
			if($this->parent->get_type()=='Base_Admin')
				Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		}
        $this->set_module_variable('settings_branch',$branch);

        $this->get_module_variable('admin_settings',($admin_settings));

        if (!$branch) {
            $x = ModuleManager::get_instance('/Base_Box|0');
            if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
            $mains = $x->get_module_variable('main');
            if(count($mains)>1)
                $x->pop_main();
            else
                $this->main_page();
            return;
        }

        $f = $this->init_module('Libs/QuickForm',__('Saving settings'),'settings');
        $f->addElement('header',null,$branch);
        $this->indicator = ': '.$branch;
        $this->settings_fields = array();
        $this->set_default_js = '';

        $us = ModuleManager::call_common_methods('user_settings');
        foreach($us as $name=>$menu) {
            if(!is_array($menu)) continue;
            foreach($menu as $k=>$v)
                if($k==$branch) {
                    if(is_string($v)) {
                        Base_BoxCommon::location($name,$v);
                    } else {
                        $this->add_module_settings_to_form($v,$f,$name);
                    }
                }    
        }

        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        Base_ActionBarCommon::add('save', __('Save'), $f->get_submit_form_href());
        Base_ActionBarCommon::add('settings',__('Restore Defaults'),'href="javascript:void(0)" onClick="'.$this->set_default_js.'"');

        if($f->validate()) {
            $this->submit_settings($f->exportValues());
            $this->set_back_location();
        } else
            $f->display();
        return;
    }

    public function submit_settings($values) {
        $reload = false;
        foreach($this->settings_fields as $k) {
            $v = isset($values[$k])?$values[$k]:0;
            $x = explode(self::$sep,$k);
            if(count($x)!=2) continue;
            list($module_name,$module_part) = $x;
            //print($module_name.':'.$module_part.'=>'.$v.'<br>');
            if($this->get_module_variable('admin_settings')) {
                Base_User_SettingsCommon::save_admin($module_name,$module_part,$v);
                continue;
            } else
                Base_User_SettingsCommon::save($module_name,$module_part,$v);

            //check reload
            $cmr = ModuleManager::call_common_methods('user_settings'); //already cached output
            if(!$reload && isset($cmr[$module_name])) {
                $menu = $cmr[$module_name];
                if(!is_array($menu)) continue;
                foreach($menu as $vv) {
                    if(!is_array($vv)) continue;
                    foreach($vv as $v) {
                        if($v['type']=='group') {
                            foreach($v['elems'] as $e)
                                if($e['name']==$module_part && isset($e['reload']) && $e['reload']!=0)
                                    $reload = true;
                        } elseif($v['name']==$module_part) {
                            if (isset($v['reload']) && $v['reload']!=0)
                                $reload = true;
                        }
                        if($reload) break;
                    }
                }
            }
        }

        Base_StatusBarCommon::message($reload?__('Setting saved - reloading page'):__('Setting saved'));
        if ($reload) eval_js('setTimeout(\'document.location=\\\'index.php\\\'\',\'1500\')',false);
        return true;
    }

    private function add_elem_to_form(array & $v,array & $defaults, $module,$admin_settings) {
        $old_name = $v['name'];
        $v['name'] = $module.self::$sep.$v['name'];
        $this->settings_fields[] = $v['name'];
        if (isset($v['rule'])) {
            if(isset($v['rule']['message']) && isset($v['rule']['type'])) $v['rule'] = array($v['rule']);
        }
        if($admin_settings)
            $value = Base_User_SettingsCommon::get_admin($module,$old_name);
        else
            $value = Base_User_SettingsCommon::get($module,$old_name);
        $defaults = array_merge($defaults,array($v['name']=>$value));
    }

    private function add_module_settings_to_form($info, &$f, $module){
        $defaults = array();
        $admin_settings = $this->get_module_variable('admin_settings');
        foreach($info as $k=>&$v){
            if($v['type']=='group')
                foreach($v['elems'] as & $vv)
                    $this->add_elem_to_form($vv,$defaults, $module,$admin_settings);
            elseif($v['type']!='hidden')
                $this->add_elem_to_form($v,$defaults, $module,$admin_settings);
            else unset($info[$k]);
        }
        $f -> add_array($info, $this->set_default_js);
        $f -> setDefaults($defaults);

    }

    public function main_page(){
        if (!Acl::is_user()) {
            print('Log in to change your settings.');
        }
        $modules = array();
        $admin_settings = $this->get_module_variable('admin_settings');

        $us = ModuleManager::call_common_methods('user_settings');
        foreach($us as $name=>$menu) {
            if(!is_array($menu)) continue;
            foreach ($menu as $k=>$v) {
				$display = false;
                if (is_array($v)) {
					foreach ($v as $k2=>$m2) {
						if (isset($m2['type']) && $m2['type']!='hidden') {
							$display=true;
							break;
						}
						if ($display) break;
					}
                } else $display = true;
				if (!$display) continue;
                if(isset($modules[$k])) {
                    if (!is_string($v) && !isset($modules[$k]['external']))
                        $modules[$k]['module_names'][] = $name;
                    else trigger_error('You cannot override this key: '.$k,E_USER_ERROR);
                } else {
                    if (!is_string($v)) $modules[$k] = array('action'=>$this->create_unique_href(array('settings_branch'=>$k)),'module_names'=>array($name));
                    elseif(!$admin_settings) $modules[$k] = array('action'=>$this->create_main_href($name,$v),'module_names'=>array($name),'external'=>true);
                }
            }
        }

        ksort($modules);

        $buttons = array();
        foreach($modules as $caption=>$arg) {
            $icon = false;
            sort($arg['module_names']);
            foreach($arg['module_names'] as $m) {
                $f = array($m.'Common','user_settings_icon');
                if(is_callable($f)) {
                    $ret = call_user_func($f);
                    if(is_array($ret)) {
                        if(isset($ret[$caption])) {
                            $icon = $ret[$caption];
                            break;
                        }
                    } elseif(is_string($ret)) {
                        $icon = $ret;
                        break;
                    }
                }
            }
            if(!$icon)
                foreach($arg['module_names'] as $m) {
                    $new = Base_ThemeCommon::get_template_file($m,'icon.png');
					if ($new) $icon = $new;
				}
            $buttons[]= array('link'=>'<a '.$arg['action'].'>'.$caption.'</a>','module'=>$arg['module_names'],'icon'=>$icon);
        }
        $theme = $this->pack_module('Base/Theme');
        $theme->assign('header', __('User Settings'));
        $theme->assign('buttons', $buttons);
        $theme->display();
    }

    public function caption() {
        return ($this->get_module_variable('admin_settings')?__('Default settings'):__('My settings')).$this->indicator;
    }
}

?>
