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
    // One entry per add_module_settings_to_form() call (a branch can be fed
    // by several modules), each the module's final $info array in original
    // definition order (header/group/field markers, post the hidden-field
    // unset()) - display_adminlte() needs this because EpesiSmartyRenderer's
    // renderHeader() buckets every header into one shared $raw['header']
    // out of sequence, so it's the only place header-to-field position
    // survives at all.
    private $adminlte_infos = array();

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
        $this->adminlte_infos = array();

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
        } elseif (Base_ThemeCommon::is_adminlte_family()) {
            $this->display_adminlte($f,$branch);
        } else
            $f->display();
        return;
    }

    /**
     * AdminLTE rendering of the generic settings-branch form built above -
     * shared by every "My settings"/admin settings screen (Base_User_Settings
     * is the one module behind all of them).
     *
     * Two things this reconstructs that EpesiSmartyRenderer::toArray() loses:
     *
     * 1. Header position. renderHeader() buckets every header element into
     *    one shared $raw['header'] array instead of leaving it in sequence
     *    with the fields around it, so which fields a given header actually
     *    precedes can't be read back from $raw at all - it has to come from
     *    $this->adminlte_infos (the original user_settings() definitions,
     *    still in their original order, captured in
     *    add_module_settings_to_form()). $header_positions below records,
     *    for each header, how many non-header fields/groups came before it;
     *    walking $raw's own (otherwise-correct) element order and splicing
     *    a header in wherever its count is reached reunites the two.
     *
     * 2. Column identity across sections. PEAR QuickForm's own group
     *    rendering (used for radio groups and for the multi-checkbox rows
     *    built by Base_Menu_QuickAccessCommon::user_settings()) concatenates
     *    each child element's rendered html into one string with no
     *    per-child breakdown available - so a group that turns out to be
     *    nothing but a uniform row of checkboxes is detected and re-split
     *    back into individual cells by regex, shown as a table with one
     *    shared header row instead of repeating each column's caption on
     *    every row (only Quick Access does this today). Separately, plain
     *    (non-group) per-row selects that repeat the exact same option list
     *    across consecutive header sections - e.g. Utils_RecordBrowserCommon's
     *    "Automatically add to favorites"/"Automatically watch" - are merged
     *    the same way: one row per module (union of labels, first-seen
     *    order), one value column per section, instead of listing every
     *    module's name again under each header. Any group that doesn't match
     *    the checkbox shape, and any select section that doesn't share an
     *    identical option list with an adjacent one, just falls back to an
     *    ordinary label/value row.
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

        // Where each header falls: 'count' = how many non-header elements
        // (each group counts as 1, matching $raw's single entry per group)
        // preceded it, across every module contributing to this branch.
        $header_positions = array();
        $source_defs = array();
        $count = 0;
        foreach ($this->adminlte_infos as $info) {
            foreach ($info as $v) {
                if ($v['type']=='header') {
                    $header_positions[] = array('count'=>$count,'label'=>$v['label']);
                } else {
                    $source_defs[$count] = array('type'=>$v['type'],'values'=>$v['values'] ?? null,'hint'=>$v['hint'] ?? null);
                    $count++;
                }
            }
        }

        $sequence = array();
        foreach ($raw as $key=>$el) {
            if (isset($skip[$key]) || !is_array($el) || !isset($el['type'])) continue;
            $sequence[] = $el;
        }

        $sections = array();
        $current = array('header'=>null,'elements'=>array(),'defs'=>array());
        $hp_idx = 0;
        foreach ($sequence as $i=>$el) {
            while ($hp_idx < count($header_positions) && $header_positions[$hp_idx]['count']===$i) {
                $sections[] = $current;
                $current = array('header'=>$header_positions[$hp_idx]['label'],'elements'=>array(),'defs'=>array());
                $hp_idx++;
            }
            $current['elements'][] = $el;
            $current['defs'][] = $source_defs[$i] ?? array('type'=>$el['type'],'values'=>null);
        }
        // Trailing headers with nothing after them (e.g. "Automatically
        // watch" when no table on this install has a watchdog category) -
        // keep the section anyway, just empty; skipped below at render time.
        while ($hp_idx < count($header_positions)) {
            $sections[] = $current;
            $current = array('header'=>$header_positions[$hp_idx]['label'],'elements'=>array(),'defs'=>array());
            $hp_idx++;
        }
        $sections[] = $current;

        $built = array();
        foreach ($sections as $sec) {
            if (!$sec['elements']) continue;
            $matrix = array();
            $captions = null;
            $plain = array();
            foreach ($sec['elements'] as $idx=>$el) {
                if ($el['type']=='group') {
                    preg_match_all($cell_re,$el['html'],$m,PREG_SET_ORDER);
                    // HTML_QuickForm_group::toHtml() joins its children with
                    // its own default separator ('&nbsp;', see vendor/openpsa's
                    // Renderer/Default.php::finishGroup()) when none was
                    // passed to addGroup() - harmless leftover once every
                    // real element has been matched out, so it doesn't
                    // disqualify the group.
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
                $el['hint'] = $sec['defs'][$idx]['hint'] ?? null;
                $plain[] = array('el'=>$el,'def'=>$sec['defs'][$idx]);
            }

            // Eligible for cross-section merging only if there's no checkbox
            // matrix/mixed content and every remaining row is a plain
            // (non-group) select sharing one identical option list.
            $select_sig = null;
            $is_select_only = !$matrix && $plain;
            foreach ($plain as $p) {
                if ($is_select_only && $p['el']['type']=='select' && is_array($p['def']['values'])) {
                    $sig = serialize(array_values($p['def']['values']));
                    if ($select_sig===null) $select_sig = $sig;
                    if ($sig===$select_sig) continue;
                }
                $is_select_only = false;
                break;
            }

            $rows = array();
            $select_rows = array();
            foreach ($plain as $p) {
                $el = $p['el'];
                if ($is_select_only) {
                    $select_rows[] = $el;
                    continue;
                }
                $el['error'] = $el['error'] ?? '';
                $rows[] = $el;
            }

            $built[] = array(
                'select_matrix'=>false,
                'header'=>$sec['header'],
                'matrix_captions'=>$captions?:array(),
                'matrix_rows'=>$matrix,
                'rows'=>$rows,
                'select_rows'=>$select_rows,
                'is_select_only'=>$is_select_only,
                'select_sig'=>$select_sig,
            );
        }

        // Merge consecutive is_select_only sections sharing the identical
        // option signature into one combined matrix.
        $final = array();
        $i = 0;
        $n = count($built);
        while ($i < $n) {
            $sec = $built[$i];
            if (!$sec['is_select_only']) { $final[] = $sec; $i++; continue; }
            $group = array($sec);
            $j = $i+1;
            while ($j < $n && $built[$j]['is_select_only'] && $built[$j]['select_sig']===$sec['select_sig']) {
                $group[] = $built[$j];
                $j++;
            }
            if (count($group) < 2) {
                // no merge partner - render its select rows as ordinary rows
                foreach ($sec['select_rows'] as $el) {
                    $el['error'] = $el['error'] ?? '';
                    $sec['rows'][] = $el;
                }
                $sec['select_rows'] = array();
                $final[] = $sec;
                $i++;
                continue;
            }
            $labels = array();
            foreach ($group as $g) foreach ($g['select_rows'] as $r) if (!in_array($r['label'],$labels,true)) $labels[] = $r['label'];
            $matrix_rows = array();
            foreach ($labels as $label) {
                $cells = array();
                foreach ($group as $g) {
                    $html = null;
                    foreach ($g['select_rows'] as $r) if ($r['label']===$label) { $html = $r['html']; break; }
                    $cells[] = $html;
                }
                $matrix_rows[] = array('label'=>$label,'cells'=>$cells);
            }
            $final[] = array(
                'select_matrix'=>true,
                'headers'=>array_map(function($g){return $g['header'];},$group),
                'matrix_rows'=>$matrix_rows,
            );
            $i = $j;
        }

        $theme->assign('branch',$branch);
        $theme->assign('sections',$final);
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
        $this->adminlte_infos[] = $info;
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

        uksort($modules,strcasecmp(...));

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
            // helpID: lets a Base_Help tutorial (see AI-shared/how-to-write-HELP.md)
            // click through to a specific settings branch, e.g. "Password" - this
            // hub has no other stable per-tile identifier (the href is a
            // create_unique_href(), regenerated per instance). Keyed by $caption
            // (already translated at this point, since every user_settings()
            // implementation builds its keys with __(), not _M() - unlike Menu_0.php's
            // helpID scheme, this one is NOT locale-stable; a tutorial written against
            // the English caption only resolves correctly for an English-locale run).
            $buttons[]= array('link'=>'<a class="card text-decoration-none h-100 shadow-sm" helpID="UserSettings_'.htmlspecialchars($caption, ENT_QUOTES).'" '.$arg['action'].'>'.$caption.'</a>','module'=>$arg['module_names'],'icon'=>$icon);
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
