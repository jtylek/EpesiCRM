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

	public function body() {
		$theme = & $this->pack_module('Base/Theme');
		$lang = & $this->init_module('Base/Lang');
		try {
			$ini = Base_ThemeCommon::get_template_file('Base_Box','default.ini');
		} catch(Exception $e) {
			print($lang->t('Unable to read Base_Box__default.ini file! Please create one, or change theme.'));
			$this->pack_module('Base/Theme/Administrator',null,'admin');
			return;
		}
		$ini_file = parse_ini_file($ini,true);
		$logged = Acl::is_user();
		$theme->assign('logged',$logged);
		$containers = array();
		$containers['main'] = array('module'=>null); //so 'main' is first in array

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
			$containers[$tag]['name'] = 'b'.$name;
		}

		$pop_main = & $this->get_module_variable('pop_main');
		if($this->isset_module_variable('main')) {
			$mains = $this->get_module_variable('main');
			if($pop_main) array_pop($mains);
			$containers['main'] = array_pop($mains);
			foreach($mains as $m)
				if(ModuleManager::is_installed($m['module'])>=0)
					$this->init_module(str_replace('/','_',$m['module']),(isset($m['constructor_arguments'])?$m['constructor_arguments']:null),(isset($m['name'])?$m['name']:null));
		} else $mains = array();


		if (isset($_REQUEST['box_main_module'])) {
			$href = $_REQUEST['box_main_module'];
			$containers['main']['module'] = $href;
			if(isset($_REQUEST['box_main_function']))
				$containers['main']['function'] = $_REQUEST['box_main_function'];
			else
				unset($containers['main']['function']);
			if(isset($_REQUEST['box_main_arguments']))
				$containers['main']['arguments'] = $_REQUEST['box_main_arguments'];
			else
				unset($containers['main']['arguments']);
			if(isset($_REQUEST['box_main_constructor_arguments']))
				$containers['main']['constructor_arguments'] = $_REQUEST['box_main_constructor_arguments'];
			else
				unset($containers['main']['constructor_arguments']);
		}
		array_push($mains,$containers['main']);
		$this->set_module_variable('main', $mains);

		$this->modules = array();
		foreach ($containers as $k => $v) {
			ob_start();
			if(ModuleManager::is_installed($v['module'])==-1) {
				if(Base_AclCommon::i_am_sa()) print($lang->t("Please install %s module or choose another theme!",array($v['module']))."<br><a ".$this->create_href(array('box_main_module'=>'Base/Setup')).">".$lang->t('Manage modules').'</a><br><a '.$this->create_href(array('box_main_module'=>'Base/Theme/Administrator')).'>'.$lang->t("Choose another theme").'</a>');
			} else {
				$module_type = str_replace('/','_',$v['module']);
				if (!isset($v['name'])) $v['name'] = null;

				if(isset($href) && $k=='main')
					$this->modules[$k] = $this->init_module($module_type,(isset($v['constructor_arguments'])?$v['constructor_arguments']:null),$v['name'],true);
				else
					$this->modules[$k] = $this->init_module($module_type,(isset($v['constructor_arguments'])?$v['constructor_arguments']:null),$v['name']);

				if($k=='main' && $pop_main) {
					$this->modules[$k]->set_reload(true);
					$pop_main = false;
				}

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
		$theme->assign('version_no',EPESI_VERSION);
		$theme->display();


	}

	public function get_main_module() {
		return isset($this->modules['main'])?$this->modules['main']:null;
	}

	public function push_main($module=null,$func=null,$args=null,$constr_args=null) {
		$mains = & $this->get_module_variable('main');
		$x = count($mains);
		$arr = $mains[$x-1];
		if(isset($module)) $arr['module'] = $module;
		if(isset($func)) $arr['function'] = $func;
		if(isset($args)) $arr['arguments'] = $args;
		if(isset($constr_args)) $arr['constructor_arguments'] = $constr_args;
		$mains[$x] = & $arr;
		location(array());
	}

	public function pop_main() {
		$mains = & $this->get_module_variable('main');
		if(count($mains)>1) {
			$this->set_module_variable('pop_main',true);
			location();
		}
	}
}
?>
