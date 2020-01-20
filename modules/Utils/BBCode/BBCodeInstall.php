<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage bbcode
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_BBCodeInstall extends ModuleInstall {
	public function install() {
		DB::CreateTable('utils_bbcode',
						'code C(64) KEY,'.
						'func C(128)',
						array('constraints'=>'')
		);
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('b','Utils_BBCodeCommon::tag_b'));
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('i','Utils_BBCodeCommon::tag_i'));
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('u','Utils_BBCodeCommon::tag_u'));
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('s','Utils_BBCodeCommon::tag_s'));
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('url','Utils_BBCodeCommon::tag_url'));
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('color','Utils_BBCodeCommon::tag_color'));
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('img','Utils_BBCodeCommon::tag_img'));
		return true;
	}

	public function uninstall() {
		DB::DropTable('utils_bbcode');
		return true;
	}

	public function info() {
		return array('Author'=>'<a href="mailto:j@epe.si">Arkadiusz Bisaga</a> (<a href="https://epe.si">Janusz Tylek</a>)', 'License'=>'MIT', 'Description'=>'BBCode parser module for epesi.');
	}

	public function simple_setup() {
		return __('EPESI Core');
	}
	public function requires($v) {
		return array(
			array('name'=>Base_RegionalSettingsInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0)
		);
	}
	public function version() {
		return array('1.0');
	}
}

?>
