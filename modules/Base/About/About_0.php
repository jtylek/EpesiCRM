<?php
/**
 * About Epesi
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package base-about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_About extends Module {
	private function get_info() {
		return @file_get_contents($this->get_module_dir().'/credits.html');
	}

	public function info() {
		print($this->get_info());
	}

	public function body() {
		Libs_LeightboxCommon::display('aboutepesi',$this->get_info(),'About');
		print('<a '.Libs_LeightboxCommon::get_open_href('aboutepesi').' '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Base_About','Click to get more info')).'><img src="images/epesi-powered.png" border=0></a>');
	}

	public function caption() {
		return "About";
	}

}

?>
