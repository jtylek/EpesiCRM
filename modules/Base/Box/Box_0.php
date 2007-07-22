<?php
/**
 * Box class.
 * 
 * This class provides basic container for other modules, with smarty as template engine.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides basic container for other modules, with smarty as template engine.
 * 
 * @package epesi-base-extra
 * @subpackage box
 */
class Base_Box extends Module {
	private $modules;
	
	public function body() {
		$theme = & $this->pack_module('Base/Theme');
		$lang = & $this->pack_module('Base/Lang');
		$ini = Base_ThemeCommon::get_template_file('Base_Box','default.ini');
		if(!$ini) {
			print($lang->t('Unable to read Base_Box__default.ini file! Please create one, or change theme.'));
			$this->pack_module('Base/Theme/Administrator',null,'admin');
			return;
		}
		$ini_file = parse_ini_file($ini,true);
		$logged = Acl::is_user();
		$theme->assign('logged',$logged);
		$containers = array();
		$containers['main'] = array(); //so 'main' is first in array
		
		$name = 0;
		foreach($ini_file as $tag=>$opts) {
			$name++; 
			if(($logged && $opts['display']=='anonymous') || (!$logged && $opts['display']=='logged')) {
				continue;
			}
			if(array_key_exists('arguments',$opts))
				eval('$containers[\''.$tag.'\'][\'arguments\']='.$opts['arguments'].';');
			$containers[$tag]['module'] = $opts['module'];
			$containers[$tag]['function'] = $opts['function'];
			$containers[$tag]['name'] = 'b'.$name;
		}
		
//		if($logged) $containers = $ini_file['Logged'];
//			else $containers = $ini_file['Anonymous'];

		if($this->isset_module_variable('main'))
			$containers['main'] = $this->get_module_variable('main');
		
		$href = $_REQUEST['box_main_module'];
		if (isset($href)) {
			$containers['main']['module'] = $href;
			$containers['main']['function'] = $_REQUEST['box_main_function'];
			$this->set_module_variable('main', $containers['main']);
			$containers['main']['arguments'] = $_REQUEST['box_main_arguments'];
		}
		
		$this->modules = array();
		foreach ($containers as $k => $v) {
			ob_start();
			if(ModuleManager::is_installed($v['module'])==-1) {
				if(Base_AclCommon::i_am_sa()) print($lang->t("Please install %s module or choose another theme!",array($v['module']))."<br><a ".$this->create_href(array('box_main_module'=>'Base/Setup')).">".$lang->t('Manage modules').'</a><br><a '.$this->create_href(array('box_main_module'=>'Base/Theme/Administrator')).'>'.$lang->t("Choose another theme").'</a>');
			} else {
				$module_type = str_replace('/','_',$v['module']);
				$this->modules[$k] = & ModuleManager::new_instance($module_type,$this,$v['name']);

				if(isset($href))
					$this->modules[$k]->clear_module_variables();

				if(method_exists($this->modules[$k],'construct')) {
					ob_start();
					$this->modules[$k]->construct();
					ob_end_clean();
				}
		
				if(isset($v['function']))
					$this->display_module($this->modules[$k],$v['arguments'],$v['function']);
				elseif($v['arguments'])
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
		return $this->modules['main'];
	}
}
?>