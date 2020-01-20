<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage CurrencyField
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CurrencyFieldInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		DB::CreateTable('utils_currency',
					'id I AUTO KEY,'.
					'symbol C(16),'.
					'code C(8),'.
					'decimal_sign C(2),'.
					'thousand_sign C(2),'.
					'decimals I1,'.
					'active I1,'.
					'default_currency I1,'.
					'pos_before I1',
					array('constraints'=>''));
		DB::Execute('INSERT INTO utils_currency (symbol, code, decimal_sign, thousand_sign, decimals, pos_before, active, default_currency) VALUES (%s, %s, %s, %s, %d, %d, %d, %d)',
					array('$', 'USD', '.', ',', 2, 1, 1, 1));
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('utils_currency');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(), 'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(), 'version'=>0),
			array('name'=>Libs_LeightboxInstall::module_name(), 'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(), 'version'=>0)
		);
	}	
}

?>