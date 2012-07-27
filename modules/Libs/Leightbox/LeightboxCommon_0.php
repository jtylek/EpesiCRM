<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage leightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_LeightboxCommon extends ModuleCommon {
	public static function get($id,$content,$header='',$big=0) {
		if(MOBILE_DEVICE) return '';
		static $init = true;
		if ($init) {
			Base_ThemeCommon::load_css('Libs/Leightbox','default',false);
			load_js('modules/Libs/Leightbox/leightbox.js');
			$init = false;
		}
		ob_start();
		print('<div id="'.$id.'" big="1" class="leightbox">');
		print('<input type="hidden" id="'.$id.'_bigsize" value="'.($big?1:0).'" />');
		if ($big) {
			eval_js('s = $(\''.$id.'\').style;'.
			's.top = \'5%\';'.
			's.left = \'5%\';'.
			's.width = \'90%\';'.
			's.height = \'90%\';'.
			's.padding = \'0px\';');
			
		}
		$smarty = Base_ThemeCommon::init_smarty();
		$smarty->assign('close_href','href="javascript:leightbox_deactivate(\''.$id.'\')"');
		$smarty->assign('content',$content);
		$smarty->assign('header',$header);
		$smarty->assign('close_label',__('Close'));
		$smarty->assign('resize_label',__('Resize'));
		$smarty->assign('close_href','href="javascript:leightbox_deactivate(\''.$id.'\')"');
		Base_ThemeCommon::display_smarty($smarty,'Libs_Leightbox');
		print('</div>');
		return ob_get_clean();
	}
	
	public static function display($id,$content,$header='',$big=0) {
		print(self::get($id,$content,$header,$big));
	}
	
	public static function get_open_href($id) {
		if(MOBILE_DEVICE) return '';
		return 'class="lbOn" rel="'.$id.'" href="javascript:void(0)"';
	}
	
	public static function close($id) {
		eval_js('leightbox_deactivate(\''.$id.'\')');
	}

	public static function open($id) {
		eval_js('leightbox_activate(\''.$id.'\');');
	}

}

?>