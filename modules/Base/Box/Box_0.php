<?php
/**
 * Box class.
 *
 * This class provides basic container for other modules, with smarty as template engine.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage box
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Box extends Module {
	private $modules;
	
	public function construct() {
		if(isset($_REQUEST['__homepage__']) && $_REQUEST['__homepage__']=='session')
			$this->set_reload(true);
	}

	public function body() {
		$theme = & $this->pack_module('Base/Theme');
		$lang = & $this->init_module('Base/Lang');
		try {
			$ini = Base_ThemeCommon::get_template_file('Base_Box','default.ini');
		} catch(Exception $e) {
			print($lang->t('Unable to read Base/Box/default.ini file! Please create one, or change theme.'));
			$this->pack_module('Base/Theme/Administrator',null,'admin');
			return;
		}
		$ini_file = parse_ini_file($ini,true);
		$logged = Acl::is_user();
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
		
		$pop_main = isset($_REQUEST['base_box_pop_main']);
		if($this->isset_module_variable('main')) {
			$mains = $this->get_module_variable('main');
			if($pop_main) array_pop($mains);
			$main = array_pop($mains);
			if(isset($main['module']) && $main['module']!=null)
				$containers['main'] = & $main;
			foreach($mains as $k=>$m)
				if(ModuleManager::is_installed($m['module'])>=0) {
					$this->freeze_module($m['module'],(isset($m['name'])?$m['name']:null));
//					error_log('freezing '.$m['name']."\n",3,'data/log2');
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
//		error_log(print_r($mains,true)."\n\n\n",3,'data/log');
		$main_length = count($mains);
		$this->set_module_variable('main', $mains);
//		$containers['main']['name'] .= '_'.$main_length;
		//print_r($containers);
//		error_log(print_r($containers['main'],true)."\n\n",3,'data/bx.log');

		$this->modules = array();
		foreach ($containers as $k => $v) {
			ob_start();
			if(ModuleManager::is_installed($v['module'])==-1) {
				if(Base_AclCommon::i_am_sa()) print($lang->t("Please install %s module or choose another theme!",array($v['module']))."<br><a ".$this->create_main_href('Base/Setup').">".$lang->t('Manage modules').'</a><br><a '.$this->create_main_href('Base/Theme/Administrator').'>'.$lang->t("Choose another theme").'</a>');
			} else {
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
		$theme->assign('version_no','<a href="http://www.epesi.org" '.Utils_TooltipCommon::open_tag_attrs($lang->ht('Check for new version')).' target="_blank">'.$lang->t('version&nbsp;%s',array(EPESI_VERSION)).'</a>');
		$theme->display();


	}

	public function get_main_module() {
		return isset($this->modules['main'])?$this->modules['main']:null;
	}

	public function push_main($module=null,$func=null,$args=null,$constr_args=null) {
		$mains = & $this->get_module_variable('main');
		$x = count($mains);
		$arr = $mains[$x-1];
		$arr['name'] = 'main_'.$x;
		if(isset($module)) $arr['module'] = $module;
		if(isset($func)) $arr['function'] = $func;
		if(isset($args)) $arr['arguments'] = $args;
		if(isset($constr_args)) $arr['constructor_arguments'] = $constr_args;
		//error_log('dodaje:'."\n".print_r($arr,true)."\n\n\n",3,'data/log');
		$mains[$x] = & $arr;
		location(array());
	}

	public function pop_main() {
		$mains = & $this->get_module_variable('main');
		if(count($mains)>1)
			location(array('base_box_pop_main'=>1));
	}
}
?>
