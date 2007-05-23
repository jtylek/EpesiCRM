<?php
/**
 * HomePage class.
 * 
 * This class provides saving any page as homepage for each user.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides saving any page as homepage for each user.
 * @package tcms-base-extra
 * @subpackage homepage
 */
class Base_HomePage extends Module {
	
	public function body($arg) {
		$lang = $this->pack_module('Base/Lang');
		
		if(Acl::is_user()) {
			global $base;
			$uid = Base_UserCommon::get_user_id(Acl::get_user());
		
			//show home page link
			print('<a '.$this->create_unique_href(array('homepage'=>'load')).'>'.$lang->t('Home page').'</a><br>');	
			//save this page as home page?
			$query_url = $_SERVER['QUERY_STRING'];
			print('<a '.$this->create_unique_href(array('homepage'=>'save')).'>'.$lang->t('Make this page you home page').'</a>');
			
			//do i need to save home_page?	
			$homepage = $this->get_unique_href_variable('homepage');
			$session = & $base->get_session();
			if($homepage=='save') {
				$this->save_home_page($uid, http_build_query($session['__module_vars__']));
				//return;
			} elseif($homepage=='load' || (array_key_exists('homepage_user_logged', $_SESSION) && $_SESSION['homepage_user_logged']==false)) {
				$home_page_addr = $this->get_home_page($uid);
//				unset($variables);
				parse_str($home_page_addr, $session['__module_vars__']);
				location(array());
			}
			
						//update history of 'am i logged'
			$_SESSION['homepage_user_logged']=true;
		
		} else {
			print('<a '.$this->create_href(array('box_main_module'=>Base_BoxCommon::get_main_module_name())).'>'.$lang->t('Home page').'</a>');
			if(array_key_exists('homepage_user_logged', $_SESSION) && $_SESSION['homepage_user_logged']==true)
				location(array('box_main_module'=>Base_BoxCommon::get_main_module_name()));
			$_SESSION['homepage_user_logged']=false;
		}
	}
	
	private function get_home_page($uid) {
		$ret = DB::Execute('SELECT url FROM home_page WHERE user_login_id=%d',$uid);
		if(!($row = $ret->FetchRow())) return false;
		return $row[0];
	}
	
	private function save_home_page($uid, $url) {
		DB::Execute('INSERT INTO home_page VALUES(%d, %s) ON DUPLICATE KEY UPDATE url=%s',array($uid, $url, $url));
	}
}
?>