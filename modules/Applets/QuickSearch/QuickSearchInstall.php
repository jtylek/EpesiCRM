<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_QuickSearchInstall extends ModuleInstall{

	public function install() {
		$ret = true;
		Base_ThemeCommon::install_default_theme($this -> get_type());
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return $ret;
	}
	public function version() {
		return array("1.0");
	}

	public static function simple_setup() {
		return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Utils/BBCode', 'version'=>0), 
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Dashboard','version'=>0));
	}

	public static function info() {
		$html="Use for quick search on contacts and companies";
		return array(
			'Description'=>$html,
			'Author'=>'bistmaster@hotmail.com',
			'License'=>'MIT');
	}	
}

?>