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
    		return array(_M('Shoutbox')=>array('__icon__'=>'comment'));
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
           	    $emps = DB::GetAssoc('SELECT l.id,'.DB::ifelse('cd.f_last_name!=\'\'',DB::concat('cd.f_last_name',DB::qstr(' '),'cd.f_first_name'),'l.login').' as name FROM user_login l LEFT JOIN contact_data_1 cd ON (cd.f_login=l.id AND cd.active=1) LEFT JOIN base_user_settings us ON (us.user_login_id=l.id AND module=\'Apps_Shoutbox\' AND variable=\'enable_im\') WHERE l.active=1 AND l.id!=%d AND (us.value=%s OR us.value is '.($adm?'':'not ').'null) AND (cd.f_first_name '.DB::like().' '.DB::concat(DB::qstr("%%"),"%s",DB::qstr("%%")).' OR cd.f_last_name '.DB::like().' '.DB::concat(DB::qstr("%%"),"%s",DB::qstr("%%")).') ORDER BY name',array($myid,serialize(1),$search,$search));
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
		return Base_UserCommon::get_user_label($search, true);
	}

	public static function notification() {
		$settings = Base_User_SettingsCommon::get('Apps_Shoutbox','notifications');
		if(!$settings) return array();

		$time = time()-24*3600;
		if($settings==2)
			$arr = DB::GetAll('SELECT ul.login, ul.id as user_id, asm.id, asm.message, asm.posted_on, asm.to_user_login_id FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id WHERE asm.posted_on>=%T AND asm.base_user_login_id!=%d AND (asm.to_user_login_id=%d OR asm.to_user_login_id is null) ORDER BY asm.posted_on DESC LIMIT 10',array($time, Base_AclCommon::get_user(), Base_AclCommon::get_user()));
		else
			$arr = DB::GetAll('SELECT ul.login, ul.id as user_id, asm.id, asm.message, asm.posted_on, asm.to_user_login_id FROM apps_shoutbox_messages asm LEFT JOIN user_login ul ON ul.id=asm.base_user_login_id WHERE asm.posted_on>=%T AND asm.base_user_login_id!=%d AND asm.to_user_login_id=%d ORDER BY asm.posted_on DESC LIMIT 10',array($time, Base_AclCommon::get_user(), Base_AclCommon::get_user()));
		if(empty($arr)) return array();
		//print it out
		$ret = array();
		$tray = array();
		foreach($arr as $row) {
			if(!$row['login']) $row['login']='Anonymous';
			$ret['shoutbox_'.$row['id']] = vsprintf('<font color="gray">[%s]</font><font color="blue">%s</font>: %s',array(Base_RegionalSettingsCommon::time2reg($row['posted_on']), $row['login'], $row['message']));
			$tray['shoutbox_'.$row['id']] = array('title'=>__('Shoutbox Message'), 'body'=>($row['to_user_login_id']?__('%s wrote to you: %s', array(Base_UserCommon::get_user_label($row['user_id'], true),$row['message'])):__('%s wrote to all: %s', array(Base_UserCommon::get_user_label($row['user_id'], true),$row['message']))));
		}

		return array('notifications'=>$ret, 'tray'=>$tray);
	}

	public static function user_settings(){
		return array(__('Misc')=>array(
			array('name'=>'enable_im','label'=>__('Allow IM with me'),'type'=>'bool','default'=>1)),
			__('Notifications')=>array(
				array('name'=>null,'label'=>__('Shoutbox'),'type'=>'header'),
				array('name'=>'notifications','label'=>__('Notify about shoutbox messages'),'type'=>'select', 'values'=>array(0=>__('no'),1=>__('only personal messages'), 2=>__('personal and public messages')), 'default'=>2),
			));
	}

	public static function create_write_to_link ($uid) {
		$ret = Base_UserCommon::get_user_label($uid, true);
		if (Acl::get_user() != $uid) $ret = "<a href=\"javascript:void(0);\" onclick=\"autoselect_add_value('shoutbox_to', ".$uid.", '".Epesi::escapeJS($ret)."');autoselect_stop_searching('shoutbox_to');jq('#shoutbox_to').change();\">".$ret.'</a>';
		return $ret;
	}

	public static function can_delete_msg($message)
	{
		if (Base_AclCommon::check_permission('Shoutbox Admin')) return true;
		if (strtotime($message['posted_on']) > (time() - 10*60) && $message['base_user_login_id'] == Base_AclCommon::get_user())
			return true;
		return false;
	}

	public static function format_message($message, $strongify = false, $view_deleted = false)
	{
		$msg_str = Utils_BBCodeCommon::parse($message['message']);
		if ($strongify) {
			$msg_str = "<strong>$msg_str</strong>";
		}
		if ($message['deleted']) {
			$msg_str = $view_deleted ? " $msg_str" : "";
			$msg_str = "<span style=\"color: #aaaaaa\">[ " . __('Deleted') . " ]$msg_str</span>";
		}
		return $msg_str;
	}
}
?>
