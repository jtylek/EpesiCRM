<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-utils
 * @subpackage frontpage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FrontPageCommon extends ModuleCommon {
	public static function display($header, $content, $info=false) {
	    $l = Variable::get('logo_file', false);
		if (!$l) $l = Base_ThemeCommon::get_template_file('images/logo-small.png');
		
		$smarty = Base_ThemeCommon::init_smarty();
		$smarty->assign('header',$header);
		$smarty->assign('contents',$content);
		$smarty->assign('info',$info);
		$smarty->assign('footer','');
		$smarty->assign('logo',$l);
		$smarty->assign('url',get_epesi_url());
		Base_ThemeCommon::display_smarty($smarty,'Utils_FrontPage','default');
	}
}
?>
