<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage bbcode
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_BBCodeInstall extends ModuleInstall {
	public function install() {
		Base_LangCommon::install_translations($this->get_type());
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
		return array('Author'=>'<a href="mailto:abisaga@telaxus.com">Arkadiusz Bisaga</a> (<a href="http://www.telaxus.com">Telaxus LLC</a>)', 'Licence'=>'MIT', 'Description'=>'BBCode parser module for epesi.');
	}

	public function simple_setup() {
		return false;
	}
	public function requires($v) {
		return array(
			array('name'=>'Base/RegionalSettings', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0)
		);
	}
	public function version() {
		return array('1.0');
	}
}

?>
