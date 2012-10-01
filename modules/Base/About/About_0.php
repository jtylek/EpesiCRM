<?php
/**
 * About Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_About extends Module {
	private function get_info() {
		return str_replace('__VERSION__',EPESI_VERSION.' rev'.EPESI_REVISION,@file_get_contents($this->get_module_dir().'/credits.html'));
	}

	public function info() {
		print($this->get_info());
	}

	public function body() {
		Libs_LeightboxCommon::display('aboutepesi',$this->get_info(),'About');
		print('<a '.Libs_LeightboxCommon::get_open_href('aboutepesi').' '.Utils_TooltipCommon::open_tag_attrs(__('Click to get more info')).'><img src="images/epesi-powered.png" border=0></a>');
	}

	public function caption() {
		return __('About');
	}

}

?>
