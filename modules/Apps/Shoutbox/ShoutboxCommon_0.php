<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage shoutbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ShoutboxCommon extends ModuleCommon {
	public static function menu() {
	    if(Base_AclCommon::check_permission('Shoutbox'))
    		return array(_M('Shoutbox')=>array());
    	return array();
	}
	
	public static function applet_caption() {
	    if(Base_AclCommon::check_permission('Shoutbox'))
    		return __('Shoutbox');
        return false;
	}

	public static function applet_info() {
	    if(Base_AclCommon::check_permission('Shoutbox'))
    		return __('Mini shoutbox'); //here can be associative array
        return '';
	}
	
	public static function user_search($search=null) {
        $myid = Base_AclCommon::get_user();
      	if(Base_User_SettingsCommon::get('Apps_Shoutbox','enable_im')) {
       	    $adm = Base_User_SettingsCommon::get_admin('Apps_Shoutbox','enable_im');
       	    if(ModuleManager::is_installed('CRM_Contacts')>=0) {
           	    $emps = DB::GetAssoc('SELECT l.id,'.DB::ifelse('cd.f_last_name!=\'\'',DB::concat('cd.f_last_name',DB::qstr(' '),'cd.f_first_name',DB::qstr(' ('),'l.login',DB::qstr(')')),'l.login').' as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) AND (l.login '.DB::like().' '.DB::concat(DB::qstr("%%"),"%s",DB::qstr("%%")).' OR cd.f_first_name '.DB::like().' '.DB::concat(DB::qstr("%%"),"%s",DB::qstr("%%")).' OR cd.f_last_name '.DB::like().' '.DB::concat(DB::qstr("%%"),"%s",DB::qstr("%%")).') ORDER BY name',array($myid,serialize(1),$search,$search,$search));
	        } else
    	        $emps = DB::GetAssoc('SELECT l.id,l.login FROM user_login l LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) AND l.login '.DB::like().' '.DB::concat(DB::qstr("%%"),"%s",DB::qstr("%%")).' ORDER BY l.login',array($myid,serialize(1),$search));
    	} else $emps = array();
    	if(ModuleManager::is_installed('Tools_WhoIsOnline')>=0) {
    	    $online = Tools_WhoIsOnlineCommon::get_ids();
    	    foreach($online as $id) {
    	        if(isset($emps[$id])) 
    	            $emps[$id] = '* '.$emps[$id] ;
    	    }
    	}
	    return $emps;
	}

	public static function user_format($search=null) {
   	    if(ModuleManager::is_installed('CRM_Contacts')>=0) {
       	    $emps = DB::GetOne('SELECT '.DB::ifelse('cd.f_last_name!=\'\'',DB::concat('cd.f_last_name',DB::qstr(' '),'cd.f_first_name',DB::qstr(' ('),'l.login',DB::qstr(')')),'l.login').' as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) WHERE l.id=%d',array($search));
        } else
  	        $emps = DB::GetOne('SELECT l.login FROM user_login l WHERE l.id=%d',array($search));
	    return $emps;
	}

	public static function tray_notification($time) {
		if(!$time)
			$time = time()-24*3600;
		$arr = DB::GetAll('SELECT ul.login, asm.id, asm.message, asm.posted_on FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id WHERE asm.posted_on>=%T AND asm.base_user_login_id!=%d AND (asm.to_user_login_id=%d OR asm.to_user_login_id is null) ORDER BY asm.posted_on DESC LIMIT 10',array($time, Base_AclCommon::get_user(), Base_AclCommon::get_user()));
		if(empty($arr)) return array();
		//print it out
		$ret = array();
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			$ret['shoutbox_'.$row['id']] = vsprintf('<font color="gray">[%s]</font><font color="blue">%s</font>: %s',array(Base_RegionalSettingsCommon::time2reg($row['posted_on']), $row['login'], $row['message']));
		}

		return array('notifications'=>$ret);
	}

	public static function user_settings(){
		return array(__('Misc')=>array(
			array('name'=>'enable_im','label'=>__('Allow IM with me'),'type'=>'bool','default'=>1)
			));
	}
}
?>
