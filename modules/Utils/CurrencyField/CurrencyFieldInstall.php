<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage CurrencyField
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CurrencyFieldInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme($this->get_type());
		DB::CreateTable('utils_currency',
					'id I AUTO KEY,'.
					'symbol C(16),'.
					'code C(8),'.
					'decimal_sign C(2),'.
					'thousand_sign C(2),'.
					'decimals I1,'.
					'active I1,'.
					'pos_before I1',
					array('constraints'=>''));
		DB::Execute('INSERT INTO utils_currency (id, symbol, code, decimal_sign, thousand_sign, decimals, pos_before, active) VALUES (%d, %s, %s, %s, %s, %d, %d, %d)',
					array(1, '$', 'USD', '.', ',', 2, 1, 1));
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('utils_currency');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Theme', 'version'=>0),
			array('name'=>'Base/Lang', 'version'=>0),
			array('name'=>'Base/User/Settings', 'version'=>0),
			array('name'=>'Utils/Tooltip', 'version'=>0),
			array('name'=>'Libs/Leightbox', 'version'=>0),
			array('name'=>'Libs/QuickForm', 'version'=>0)
		);
	}	
}

?>