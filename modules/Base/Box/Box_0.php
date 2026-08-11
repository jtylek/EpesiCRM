<?php
/**
 * Box class.
 *
 * This class provides basic container for other modules, with smarty as template engine.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage box
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Box extends Module {
    private $modules;

    public function construct() {
//      if(isset($_REQUEST['__homepage__']) && $_REQUEST['__homepage__']=='session')
//          $this->set_reload(true);
    }

    public function body() {
        if (isset(Base_BoxCommon::$override_box_main)) {
            $this->pack_module(Base_BoxCommon::$override_box_main);
            return;
        }

        // Base_Box renders on every page, logged in or not, so this is the one
        // place a theme's framework assets need requesting.
        Base_ThemeCommon::load_theme_assets();

        $theme = $this->pack_module(Base_Theme::module_name());
		$ini = Base_BoxCommon::get_ini_file();
		
        if (!$ini) {
            print(__('Unable to read Base/Box/default.ini file! Please create one, or change theme.'));
            $this->pack_module(Base_Theme_Administrator::module_name(),null,'admin');
            return;
        }
        $ini_file = parse_ini_file($ini,true);
        $logged = Base_AclCommon::is_user();
        $theme->assign('logged',$logged);
        $containers = array();
        $containers['main'] = array('module'=>null,'name'=>''); //so 'main' is first in array

        $name = 0;
        foreach($ini_file as $tag=>$opts) {
            $name++;
            if(($logged && $opts['display']=='anonymous') || (!$logged && $opts['display']=='logged')) {
                continue;
            }
            if(isset($opts['function'])) {
                $containers[$tag]['function'] = $opts['function'];
                $containers[$tag]['arguments'] = null;
            }
            if(isset($opts['arguments']))
                $containers[$tag]['arguments'] = $opts['arguments'];
            if(isset($opts['module']))
                $containers[$tag]['module'] = $opts['module'];
            else
                trigger_error('No module specified.',E_USER_ERROR);
            $containers[$tag]['name'] = $tag;
        }

        if(isset($containers['main']))
            $containers['main']['name'] = 'main_0';

        if(isset($_REQUEST['base_box_pop_main'])) {
            $pop_main = $_REQUEST['base_box_pop_main'];
            unset($_REQUEST['base_box_pop_main']);
        } else {
            $pop_main = false;
        }
        if(isset($_REQUEST['base_box_nav_back'])) {
            $nav_back = true;
            unset($_REQUEST['base_box_nav_back']);
        } else {
            $nav_back = false;
        }
        if($this->isset_module_variable('main')) {
            $mains = $this->get_module_variable('main');
            if($pop_main) {
                while($pop_main--) array_pop($mains);
                $pop_main = true;
            }
            $main = array_pop($mains);
            if(isset($main['module']) && $main['module']!=null)
                $containers['main'] = & $main;
            foreach($mains as $k=>$m)
                if(ModuleManager::is_installed($m['module'])>=0) {
                    $this->freeze_module($m['module'],($m['name'] ?? null));
                }
        } else $mains = array();

        // Separate history stack just for the ActionBar's global Back button
        // (Base_ActionBar_0.php) - deliberately NOT folded into $mains above,
        // since Base_User_Settings and others key off count($mains)>1 to
        // detect they were explicitly push_main()'d (Base_BoxCommon::
        // push_module); growing $mains on ordinary menu navigation too would
        // make that check misfire and auto-pop screens reached normally.
        $nav_history = $this->isset_module_variable('nav_history') ? $this->get_module_variable('nav_history') : array();

        if ($nav_back) {
            $prev_main = array_pop($nav_history);
            if ($prev_main !== null) {
                $containers['main'] = $prev_main;
                $pop_main = true;
            }
        }

        if (isset($_REQUEST['box_main_href'])) {
            if(!isset($_SESSION['client']['base_box_hrefs']))
                $_SESSION['client']['base_box_hrefs'] = array();
            $hs = & $_SESSION['client']['base_box_hrefs'];
            if(isset($hs[$_REQUEST['box_main_href']])) {
                $rh = $hs[$_REQUEST['box_main_href']];
                $href = $rh['m'];

                // Remember the screen we're navigating away from, so a later
                // global Back click can return to it. Skipped on the very
                // first load (no real previous screen) and on a repeat click
                // of the same target (already there - nothing to go back to).
                $prev_main = $containers['main'];
                $new_sig = serialize(array($href, $rh['f'] ?? null, $rh['a'] ?? null));
                $prev_sig = serialize(array($prev_main['module'] ?? null, $prev_main['function'] ?? null, $prev_main['arguments'] ?? null));
                if (!empty($prev_main['module']) && $new_sig !== $prev_sig) {
                    $nav_history[] = $prev_main;
                    if (count($nav_history) > 10) array_shift($nav_history);
                }

                $containers['main']['module'] = $href;
                if(isset($rh['f']))
                    $containers['main']['function'] = $rh['f'];
                else
                    unset($containers['main']['function']);
                if(isset($rh['a']))
                    $containers['main']['arguments'] = $rh['a'];
                else
                    unset($containers['main']['arguments']);
                if(isset($rh['c']))
                    $containers['main']['constructor_arguments'] = $rh['c'];
                else
                    unset($containers['main']['constructor_arguments']);

                $mains = array();
                $pop_main = true;
            }
            unset($_REQUEST['box_main_href']);
            $hs = array();
        }
        // Same reasoning as the $mains freeze loop above - a module tracked
        // in nav_history isn't rendered as 'main' this request, but still
        // needs its child-instance slot reserved on Base_Box so a fresh
        // (clear_vars) main module elsewhere can't be mistaken for it.
        foreach ($nav_history as $m)
            if (!empty($m['module']) && ModuleManager::is_installed($m['module'])>=0)
                $this->freeze_module($m['module'],($m['name'] ?? null));
        array_push($mains,$containers['main']);
        $main_length = count($mains);
        $this->set_module_variable('main', $mains);
        $this->set_module_variable('nav_history', $nav_history);
//      Epesi::alert(print_r($mains,true));
//      $containers['main']['name'] .= '_'.$main_length;
        //print_r($containers);

        $this->modules = array();
        foreach ($containers as $k => $v) {
            ob_start();
            if(ModuleManager::is_installed($v['module'])!=-1) {
                $module_type = str_replace('/','_',$v['module']);
                if (!isset($v['name'])) $v['name'] = null;

                if(isset($href) && $k=='main')
                    $this->modules[$k] = $this->init_module($module_type,($v['constructor_arguments'] ?? null),$v['name'],true);
                else
                    $this->modules[$k] = $this->init_module($module_type,($v['constructor_arguments'] ?? null),$v['name']);

                if($k=='main' && $pop_main)
                    $this->modules[$k]->set_reload(true);

                if(isset($v['function']))
                    $this->display_module($this->modules[$k],$v['arguments'] ?? null,$v['function']);
                elseif(isset($v['arguments']))
                    $this->display_module($this->modules[$k],$v['arguments']);
                else
                    $this->display_module($this->modules[$k]);
            }
            $theme->assign($k,ob_get_contents());
            ob_end_clean();
        }


        //main output
		$version_no = Base_BoxCommon::update_version_check_indicator();

		if (SUGGEST_DONATION)
			$theme->assign('donate',Utils_TooltipCommon::create('<a target="_blank" href="http://epe.si/donate/">'.__('Support EPESI!').'</a>', '<center>'.__('If you find our software useful, please support us by making a %s.', array(__('donation'))).'<br/>'.__('Your funding will help to ensure continued development of this project.').'<br/>'.__('Click for details.').'</center>', false, 500));
			
		// Consider moving this code properly as initated module by *.ini file
		$theme->assign('home', array('href'=>Base_HomePageCommon::get_href(), 'label'=>__('Home')));
		
        $theme->assign('version_no',$version_no);
        $theme->display();

    }

    public function get_main_module() {
        return $this->modules['main'] ?? null;
    }

    public function push_main($module=null,$func=null,$args=null,$constr_args=null,$name=null,$replace=false) {
        static $pushed = false;
        if($pushed)
            return;
//          trigger_error('Double push box!',E_USER_ERROR);
        $pushed = true;
        $mains = & $this->get_module_variable('main');
        if($replace) {
            $arr = array_pop($mains);
            $x = count($mains);
        } else {
            $x = count($mains);
            $arr = $mains[$x-1];
        }
        if(isset($name)) {
            $arr['name'] = $name;
        } else {
            $arr['name'] = 'main_'.md5(microtime(true));
        }
        if(isset($module)) $arr['module'] = $module;
        if(isset($func)) $arr['function'] = $func;
        if(isset($args)) $arr['arguments'] = $args;
        if(isset($constr_args)) $arr['constructor_arguments'] = $constr_args;
        $mains[$x] = & $arr;
        if($x>=5) array_shift($mains);
        location(array());
    }
    
    public function replace_main($module=null,$func=null,$args=null,$constr_args=null,$name=null) {
        $this->push_main($module,$func,$args,$constr_args,$name,true);
    }

    public function pop_main($c=1) {
        static $poped = false;
        if($poped)
            return;
//          trigger_error('Double pop box!',E_USER_ERROR);
        $poped = true;
//        $mains = & $this->get_module_variable('main');
        location(array('base_box_pop_main'=>$c));
    }

    /**
     * Returns to the previous screen recorded in nav_history (see body()) -
     * the generic fallback the ActionBar's global Back button uses when the
     * currently active module hasn't registered its own Back action.
     */
    public function nav_back() {
        static $went_back = false;
        if($went_back)
            return;
        $went_back = true;
        location(array('base_box_nav_back'=>1));
    }

    public function has_nav_history() {
        $h = $this->isset_module_variable('nav_history') ? $this->get_module_variable('nav_history') : array();
        return count($h) > 0;
    }
}
?>
