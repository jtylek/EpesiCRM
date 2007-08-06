<?php
/**
 * Navigation component: back, refresh, forward.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage navigation
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Navigation extends Module {
	private $lang = null;
	
	public function body($arg) {
		global $base;
		
		$lang = & $this->init_module('Base/Lang');
		$theme = & $this->init_module('Base/Theme');
		
		if(History::is_back())
			$theme->assign('back','<a '.$this->create_callback_href(array('Base_Navigation','back')).'>'.$lang->t('<<').'</a>');
		else
			$theme->assign('back',$lang->t('<<'));
		
		$theme->assign('reload','<a '.$this->create_callback_href(array('Base_Navigation','reload_page')).'>'.$lang->t('@').'</a>');
		
		if(History::is_forward())
			$theme->assign('next','<a '.$this->create_callback_href(array('Base_Navigation','forward')).'>'.$lang->t('>>').'</a>');
		else
			$theme->assign('next', $lang->t('>>'));
		
		$theme->display();
	}
	
	public static function back() {
		global $base;
		on_exit(array('History','back'));
		return false;
	}
	
	public static function forward() {
		global $base;
		on_exit(array('History','forward'));
		return false;
	}
	
	public static function reload_page() {
		location(array());
		return false;
	}
}
?>
