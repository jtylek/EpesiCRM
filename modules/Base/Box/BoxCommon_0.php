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
 * @subpackage backup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BoxCommon extends ModuleCommon {
	public static function get_main_module_name() {
		$ini = Base_ThemeCommon::get_template_file('Base_Box','default.ini');
		if(!$ini) {
			print(Base_LangCommon::ts('Unable to read Base_Box.ini file! Please create one, or change theme.'));
			return;
		}
		$containers = parse_ini_file($ini,true);
		return $containers['main']['module'];
	}
	
	public static function create_href_array($parent_module,$module,$function=null,array $arguments=null, array $constructor_args=null) {
		if(!isset($_SESSION['client']['base_box_hrefs']))
			$_SESSION['client']['base_box_hrefs'] = array();
		$hs = & $_SESSION['client']['base_box_hrefs'];

		$r=array('m'=>$module, 'p'=>(!$parent_module)?'':$parent_module->get_path());
		if(isset($arguments))
			$r['a']=$arguments;
		if(isset($constructor_args))
			$r['c']=$constructor_args;
		if(isset($function))
			$r['f']=$function;
			
		$md = md5(serialize($r));
		$hs[$md] = $r;

		return array('box_main_href'=>$md);
	}
	
	public static function create_href($parent_module,$module,$function=null,array $arguments=null,array $constructor_args=null,array $other_href_args=array()) {
		return Module::create_href(array_merge($other_href_args,Base_BoxCommon::create_href_array($parent_module, $module, $function, $arguments, $constructor_args)));
	}

	public static function create_href_js($parent_module,$module,$function=null,array $arguments=null,array $constructor_args=null,array $other_href_args=array()) {
		return Module::create_href_js(array_merge($other_href_args,Base_BoxCommon::create_href_array($parent_module, $module, $function, $arguments, $constructor_args)));
	}
	
	public static function location($module,$function=null,array $arguments=null,array $constructor_args=null,array $other_href_args=array()) {
		return location(array_merge($other_href_args,Base_BoxCommon::create_href_array(null, $module, $function, $arguments, $constructor_args)));	
	}
}

Module::register_method("create_main_href",array("Base_BoxCommon","create_href"));
Module::register_method("create_main_href_js",array("Base_BoxCommon","create_href_js"));

?>
