<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage leightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!MOBILE_DEVICE) {
	Base_ThemeCommon::load_css('Libs/Leightbox','default',false);
	load_js('modules/Libs/Leightbox/leightbox.js');
}

class Libs_LeightboxCommon extends ModuleCommon {
	public static function get($id,$content,$header='') {
		if(MOBILE_DEVICE) return '';
		ob_start();
		print('<div id="'.$id.'" class="leightbox">');
		$smarty = Base_ThemeCommon::init_smarty();
		$smarty->assign('close_href','href="javascript:leightbox_deactivate(\''.$id.'\')"');
		$smarty->assign('content',$content);
		$smarty->assign('header',$header);
		Base_ThemeCommon::display_smarty($smarty,'Libs_Leightbox');
		print('</div>');
		return ob_get_clean();
	}
	
	public static function display($id,$x,$header='') {
		print(self::get($id,$x,$header));
	}
	
	public static function get_open_href($id) {
		if(MOBILE_DEVICE) return '';
		return 'class="lbOn" rel="'.$id.'" href="javascript:void(0)"';
	}
}

?>