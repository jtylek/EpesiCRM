<?php
/**
 * Box class.
 *
 * This class provides basic container for other modules, with smarty as template engine.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
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
        $theme = $this->pack_module('Base/Theme');
        $ini = Base_ThemeCommon::get_template_file('Base_Box','default.ini');
        if (!$ini) {
            print(__('Unable to read Base/Box/default.ini file! Please create one, or change theme.'));
            $this->pack_module('Base/Theme/Administrator',null,'admin');
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
                    $this->freeze_module($m['module'],(isset($m['name'])?$m['name']:null));
                }
        } else $mains = array();


        if (isset($_REQUEST['box_main_href'])) {
            if(!isset($_SESSION['client']['base_box_hrefs']))
                $_SESSION['client']['base_box_hrefs'] = array();
            $hs = & $_SESSION['client']['base_box_hrefs'];
            $hs_gc = & $this->get_module_variable('__hrefs_gc__',0);
            if(isset($hs[$_REQUEST['box_main_href']])) {
                $rh = $hs[$_REQUEST['box_main_href']];
                $href = $rh['m'];
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
            $hs_gc++;
            if($hs_gc>4) {
                foreach($hs as $k=>$v) {
                    if(!$v['p'] || !ModuleManager::get_instance($v['p'])) {
                        unset($hs[$k]);
                    }
                }
                $hs_gc=0;
            }
        }
        array_push($mains,$containers['main']);
        $main_length = count($mains);
        $this->set_module_variable('main', $mains);
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
                    $this->modules[$k] = $this->init_module($module_type,(isset($v['constructor_arguments'])?$v['constructor_arguments']:null),$v['name'],true);
                else
                    $this->modules[$k] = $this->init_module($module_type,(isset($v['constructor_arguments'])?$v['constructor_arguments']:null),$v['name']);

                if($k=='main' && $pop_main)
                    $this->modules[$k]->set_reload(true);

                if(isset($v['function']))
                    $this->display_module($this->modules[$k],isset($v['arguments'])?$v['arguments']:null,$v['function']);
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
			$theme->assign('donate',Utils_TooltipCommon::create('<a target="_blank" href="http://epe.si/cost">'.__('Support EPESI!').'</a>', '<center>'.__('If you find our software useful, please support us by making a donation.<br>Your funding will help to ensure continued development of this project.<br>Click for details.').'</center>', false, 500));
			
		// Consider moving this code properly as initated module by *.ini file
		$theme->assign('home', array('href'=>Base_HomePageCommon::get_href(), 'label'=>__('Home')));
		$theme->assign('help', array('href'=>Base_MainModuleIndicatorCommon::get_href(), 'label'=>__('Help')));
		
        $theme->assign('version_no',$version_no);
        $theme->display();

    }

    public function get_main_module() {
        return isset($this->modules['main'])?$this->modules['main']:null;
    }

    public function push_main($module=null,$func=null,$args=null,$constr_args=null,$name=null) {
        static $pushed = false;
        if($pushed)
            return;
//          trigger_error('Double push box!',E_USER_ERROR);
        $pushed = true;
        $mains = & $this->get_module_variable('main');
        $x = count($mains);
        $arr = $mains[$x-1];
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

    public function pop_main($c=1) {
        static $poped = false;
        if($poped)
            return;
//          trigger_error('Double pop box!',E_USER_ERROR);
        $poped = true;
//        $mains = & $this->get_module_variable('main');
        location(array('base_box_pop_main'=>$c));
    }
}
?>
