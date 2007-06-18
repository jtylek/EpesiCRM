<?php
/**
 * User_Settings class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_SettingsCommon {
	public static function tool_menu(){
		if (!Base_AclCommon::i_am_user()) return array();
		global $base;
		$modules = array(); 
		foreach($base->modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'user_settings')) {
				$menu = call_user_func(array($obj['name'].'Common','user_settings'));
				if(!is_array($menu)) continue;
				foreach($menu as $k=>$v)
					if ($v!='callbody') $modules[$k] = array('module'=>$obj['name'].'::'.$k);
					else $modules[$k] = array('box_main_module'=>$obj['name']);
			}
		}
//		return array('My settings'=>array_merge(array('__submenu__'=>1),$modules));
		return array('My settings'=>array_merge(array('__submenu__'=>1,'Control panel'=>array('__weight__'=>-10,'module'=>'__NONE__'),'__split__'=>1),$modules));
	}
	
	public static function get_user_settings($module,$name){
		if (!Base_AclCommon::i_am_user()) return;
		$module = str_replace('/','_',$module);
		static $variables;
		if (isset($variables[$module.'__'.$name]))
			return $variables[$module.'__'.$name];
		$val = null;
		$module = str_replace('/','_',$module);
		if (Base_AclCommon::i_am_user())
			$val = DB::GetOne('SELECT value FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Base_UserCommon::get_my_user_id(),$module,$name));
		if (!isset($val)) {
			if(method_exists($module.'Common', 'user_settings')) {
				$menu = call_user_func(array($module.'Common','user_settings'));
				if(is_array($menu))
					foreach($menu as $v)
						foreach($v as $v2)
							if ($v2['name']==$name) {
								$variables[$module.'__'.$name] = $v2['default'];
								return $v2['default'];
							}
				return null;
			} else {
				trigger_error('There is no such module as '.$module,E_USER_ERROR);
			}
		}
		$variables[$module.'__'.$name] = $val;
		return $val;
	}

	public static function save_user_settings($module,$name,$value){
		if (!Base_AclCommon::i_am_user()) return;
		$module = str_replace('/','_',$module);
		$def = null;
		if(method_exists($module.'Common', 'user_settings')) {
			$menu = call_user_func(array($module.'Common','user_settings'));
			if(!is_array($menu)) continue;
			foreach($menu as $v){
				foreach($v as $v2)
					if ($v2['name']==$name) {
						$def = $v2['default'];
						break;
					}
				if ($def!=null) break;
			}
		} else return false;
		if ($value==$def) {
			DB::Execute('DELETE FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Base_UserCommon::get_my_user_id(),$module,$name));
		} else {
			$val = DB::GetOne('SELECT value FROM base_user_settings WHERE user_login_id=%d AND module=%s AND variable=%s',array(Base_UserCommon::get_my_user_id(),$module,$name));
			if (!$val)
				DB::Execute('INSERT INTO base_user_settings VALUES (%d,%s,%s,%s)',array(Base_UserCommon::get_my_user_id(),$module,$name,$value));
			else
				DB::Execute('UPDATE base_user_settings SET value=%s WHERE user_login_id=%d AND module=%s AND variable=%s',array($value,Base_UserCommon::get_my_user_id(),$module,$name));
		}
		return true;
	}
}

?>