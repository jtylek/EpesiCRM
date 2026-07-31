<?php
/**
 * User_Settings class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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

        $f = $this->init_module(Libs_QuickForm::module_name(),__('Saving settings'),'settings');
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

        Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){'.$f->get_submit_form_js().'}');

        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        Base_ActionBarCommon::add('save', __('Save'), $f->get_submit_form_href());
        Base_ActionBarCommon::add('settings',__('Restore Defaults'),'href="javascript:void(0)" onClick="'.$this->set_default_js.'"');

        if($f->validate()) {
            $this->submit_settings($f->exportValues());
            $this->set_back_location();
        } elseif (Base_ThemeCommon::get_default_template()=='adminlte') {
            $this->display_adminlte($f,$branch);
        } else
            $f->display();
        return;
    }

    /**
     * AdminLTE rendering of the generic settings-branch form built above -
     * shared by every "My settings"/admin settings screen (Base_User_Settings
     * is the one module behind all of them). PEAR QuickForm's own group
     * rendering (used for radio groups and for the multi-checkbox rows built
     * by Base_Menu_QuickAccessCommon::user_settings()) concatenates each
     * child element's rendered html into one string with no per-child
     * breakdown available from EpesiSmartyRenderer's array form - so a group
     * that turns out to be nothing but a uniform row of checkboxes (only
     * Quick Access does this today) is detected and re-split back into
     * individual cells by regex, letting it be shown as a real table with one
     * shared header row instead of repeating each column's caption on every
     * row. Any group that doesn't match that exact shape (or any plain
     * field) just falls back to an ordinary label/value row.
     */
    private function display_adminlte($f,$branch) {
        require_once('include/EpesiSmartyRenderer.php');
        $renderer = new EpesiSmartyRenderer();
        $theme = $this->pack_module(Base_Theme::module_name());
        $f->assign_theme('form',$theme,$renderer);
        $raw = $renderer->toArray();

        $skip = array('frozen'=>1,'javascript'=>1,'attributes'=>1,'hidden'=>1,'requirednote'=>1,'errors'=>1,'header'=>1);
        // Deliberately checkbox-only: GenericBrowser/Planner build radio
        // groups the same way (addGroup()), and those must keep rendering as
        // an ordinary single row, not get matrix-ified into bare cells.
        $cell_re = '/(<input\b(?=[^>]*\btype=["\']checkbox["\'])[^>]*>)(?:<label\b[^>]*>(.*?)<\/label>)?/is';

        $matrix = array();
        $captions = null;
        $rows = array();
        foreach ($raw as $key=>$el) {
            if (isset($skip[$key]) || !is_array($el) || !isset($el['type'])) continue;
            if ($el['type']=='group') {
                preg_match_all($cell_re,$el['html'],$m,PREG_SET_ORDER);
                // HTML_QuickForm_group::toHtml() joins its children with its
                // own default separator ('&nbsp;', see vendor/openpsa's
                // Renderer/Default.php::finishGroup()) when none was passed
                // to addGroup() - harmless leftover once every real element
                // has been matched out, so it doesn't disqualify the group.
                $rest = preg_replace($cell_re,'',$el['html']);
                $rest = trim(str_ireplace('&nbsp;','',strip_tags($rest)));
                if ($m && $rest==='') {
                    $cells = array();
                    $caps = array();
                    foreach ($m as $one) {
                        $cells[] = $one[1];
                        $caps[] = trim($one[2] ?? '');
                    }
                    if ($captions===null) $captions = $caps;
                    if ($caps===$captions) {
                        $matrix[] = array('label'=>$el['label'],'cells'=>$cells);
                        continue;
                    }
                }
            }
            $el['error'] = $el['error'] ?? '';
            $rows[] = $el;
        }

        $extra_headers = array();
        foreach (($raw['header'] ?? array()) as $hkey=>$hval)
            if ($hkey!==0) $extra_headers[] = $hval;

        $theme->assign('branch',$branch);
        $theme->assign('extra_headers',$extra_headers);
        $theme->assign('matrix_captions',$captions?:array());
        $theme->assign('matrix_rows',$matrix);
        $theme->assign('rows',$rows);
        $theme->display('settings_form');
    }

    public function submit_settings($values) {
        $reload = false;
        foreach($this->settings_fields as $k) {
            $v = $values[$k] ?? 0;
            $x = explode(self::$sep,$k);
            if(count($x)!=2) continue;
            [$module_name, $module_part] = $x;
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
        else {
            $value = Base_User_SettingsCommon::get($module,$old_name);
            // "Restore Defaults" (the ActionBar button built from
            // $this->set_default_js in body()) is driven by $v['default'],
            // which Libs_QuickForm's add_array()/get_element_by_array() bake
            // straight into that reset JS. Left as this field's own
            // hardcoded literal (whatever user_settings() declared), it
            // ignored any site-wide default an administrator configured via
            // the "Default settings" screen (admin_settings=true, this same
            // method's own get_admin()/save_admin() branch above) -
            // regular users' "Restore Defaults" ought to reset to that
            // admin-configured value, not silently bypass it. get_admin()
            // already falls back to the hardcoded literal itself
            // (get_default()) when nothing's been admin-configured, so this
            // is a strict superset of the old behaviour, never a narrower one.
            $v['default'] = Base_User_SettingsCommon::get_admin($module,$old_name);
        }
        $defaults = array_merge($defaults,array($v['name']=>$value));
    }

    private function add_module_settings_to_form($info, &$f, $module){
        $defaults = array();
        $admin_settings = $this->get_module_variable('admin_settings');
        foreach($info as $k=>&$v){
            $max_len = 64;
            if(isset($v['name']) && strlen($v['name'])>$max_len) throw new Exception("Variable name too long. Max length is $max_len.");
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
            $icon = null;
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
            $buttons[]= array('link'=>'<a class="card text-decoration-none h-100 shadow-sm" '.$arg['action'].'>'.$caption.'</a>','module'=>$arg['module_names'],'icon'=>$icon);
        }
        $theme = $this->pack_module(Base_Theme::module_name());
        $theme->assign('header', __('User Settings'));
        $theme->assign('buttons', $buttons);
        $theme->display();
    }

    public function caption() {
        return ($this->get_module_variable('admin_settings')?__('Default settings'):__('My settings')).$this->indicator;
    }
}

?>
