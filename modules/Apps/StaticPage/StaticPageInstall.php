<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage staticpage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_StaticPageInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		$ret = true;
		$ret &= DB::CreateTable('apps_staticpage_pages','
			id I AUTO KEY,
			path C(255) UNIQUE NOTNULL,
			title C(255) NOTNULL,
			content X',
			array('constraints'=>''));
		if(!$ret){
			print('Unable to create table pages.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme('Apps/StaticPage');
		return $ret;
	}
	
	public function uninstall() {
		$ret = true;
		$x = DB::Execute('SELECT id FROM apps_staticpage_pages');
		while($row=$x->FetchRow())
			Utils_CustomMenuCommon::delete('staticpage:'.$row['id']);
		$ret &= DB::DropTable('apps_staticpage_pages');
		Base_ThemeCommon::uninstall_default_theme('Apps/StaticPage');
		return $ret;
	}
	
	public static function info() {
		return array('Author'=>'<a href="mailto:pbukowski@telaxus.com">Paul Bukowski</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'License'=>'MIT', 'Description'=>'Simple WIKI pages');
	}
	
	public static function simple_setup() {
		return true;
	}
	
	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0),
			array('name'=>'Utils/CustomMenu','version'=>0),
			array('name'=>'Libs/FCKeditor','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}
}

?>