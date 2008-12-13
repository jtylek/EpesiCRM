<?php
/**
 * Navigation component: back, refresh, forward.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage navigation
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Navigation extends Module {
	private $lang = null;
	
	public function body() {
		$theme = & $this->init_module('Base/Theme');
		
		if(History::is_back())
			$theme->assign('back','<a '.$this->create_callback_href(array('Base_Navigation','back')).'>'.$this->t('<<').'</a>');
		else
			$theme->assign('back',$this->t('<<'));
		
		$theme->assign('reload','<a '.$this->create_callback_href(array('Base_Navigation','reload_page')).'>'.$this->t('@').'</a>');
		
		if(History::is_forward())
			$theme->assign('next','<a '.$this->create_callback_href(array('Base_Navigation','forward')).'>'.$this->t('>>').'</a>');
		else
			$theme->assign('next', $this->t('>>'));
		
		$theme->display();
	}
	
	public static function back() {
		on_exit(array('History','back'));
		return false;
	}
	
	public static function forward() {
		on_exit(array('History','forward'));
		return false;
	}
	
	public static function reload_page() {
		location(array());
		return false;
	}
}
?>
