<?php
/**
 * Popup message to the user
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage Messenger
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_MessengerCommon extends ModuleCommon {
	public static function applet_caption() {
		return __('Messenger alarms');
	}

	public static function applet_info() {
		return __('Displays last alarms');
	}
	
	public static function delete_by_parent_module($m) {
		$ret = DB::Execute('SELECT id FROM utils_messenger_message WHERE parent_module=%s',array($m));
		while($row = $ret->FetchRow())
			DB::Execute('DELETE FROM utils_messenger_users WHERE message_id=%d',array($row['id']));
		DB::Execute('DELETE FROM utils_messenger_message WHERE parent_module=%s',array($m));
	}
	
	public static function delete_by_id($id) {
		$mid = md5($id);
		$ret = DB::Execute('SELECT id FROM utils_messenger_message WHERE page_id=\''.$mid.'\'');
		while($row = $ret->FetchRow())
			DB::Execute('DELETE FROM utils_messenger_users WHERE message_id=%d',array($row['id']));
		DB::Execute('DELETE FROM utils_messenger_message WHERE page_id=\''.$mid.'\'');

	}
	
	public static function get_alarms($id) {
		return DB::GetAssoc('SELECT id, alert_on FROM utils_messenger_message WHERE page_id=%s', array(md5($id)));
	}
	
	public static function update_time($id, $time) {
		DB::Execute('UPDATE utils_messenger_message SET alert_on=%T WHERE id=%s', array($time, $id));
	}
	
	public static function add($id,$parent_type,$message,$alert_on, $callback_method,$callback_args=null,$users=null) {
		$callback_args = isset($callback_args)?((is_array($callback_args))?$callback_args:array($callback_args)):array();
		if(!isset($users)) $users = Acl::get_user();
		DB::Execute('INSERT INTO utils_messenger_message(page_id,parent_module,message,callback_method,callback_args,created_on,created_by,alert_on) VALUES(%s,%s,%s,%s,%s,%T,%d,%T)',array(md5($id),$parent_type,$message,serialize($callback_method),serialize($callback_args),time(),Acl::get_user(),$alert_on));
		$id = DB::Insert_ID('utils_messenger_message','id');
		if(is_array($users)) {
			foreach($users as $k) {
				if(is_numeric($k) && (Base_User_SettingsCommon::get('Utils_Messenger','allow_other',$k) || Acl::get_user()==$k))
					DB::Execute('INSERT INTO utils_messenger_users(message_id,user_login_id) VALUES (%d,%d)',array($id,$k));
			}
		} elseif(is_numeric($users))
			DB::Execute('INSERT INTO utils_messenger_users(message_id,user_login_id) VALUES (%d,%d)',array($id,$users));
	}

	public static function tray_notification() {
		$arr = DB::GetAll('SELECT m.* FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.user_login_id=%d AND u.done=0 AND m.alert_on<%T',array(Acl::get_user(),time()));
		$ret = array();
		foreach($arr as $row) {
			ob_start();
			$m = call_user_func_array(unserialize($row['callback_method']),unserialize($row['callback_args']));
			ob_clean();
			$ret['messenger_'.$row['id']] = __('Alert on: %s',array(Base_RegionalSettingsCommon::time2reg($row['alert_on'])))."<br>".str_replace("\n",'<br>',$m).($row['message']?"<br>".__('Alarm comment: %s',array($row['message'])):'');
		}
		return array('alerts'=>$ret);
	}
	
	public static function user_settings(){
		return array(__('Alerts')=>array(
			array('name'=>'mail','label'=>__('E-mail'),'type'=>'text','default'=>'',
					'rule'=>array('type'=>'email',
						'message'=>__('Invalid e-mail address'))),
			array('name'=>'always_follow_me','label'=>__('Always follow me'),'type'=>'bool','default'=>0,
					'rule'=>array('type'=>'callback',
						'func'=>array('Utils_MessengerCommon','check_follow'),
						'message'=>__('E-mail required if you want to be followed.'),
						'param'=>'__form__')),
			array('name'=>'allow_other','label'=>__('Allow other users to set up alerts for me'),'type'=>'bool','default'=>0)
			));
	}
	
	public static function check_follow($v, $f) {
		if(!$v) return true;
		return $f->exportValue('Utils_Messenger__mail')!='';
	}
	
	public static function cron() {
		$arr = DB::GetAll('SELECT m.*,u.* FROM utils_messenger_message m INNER JOIN utils_messenger_users u ON u.message_id=m.id WHERE u.follow=0 AND m.alert_on+INTERVAL 4 minute<%T',array(time()));
		$ret = '';
		foreach($arr as $row) {
			Acl::set_user($row['user_login_id']);
			$always_follow = Base_User_SettingsCommon::get('Utils_Messenger','always_follow_me');
			if(!$always_follow && $row['done']) continue;
			ob_start();
			$fret = call_user_func_array(unserialize($row['callback_method']),unserialize($row['callback_args']));
			ob_end_clean();
			DB::Execute('UPDATE utils_messenger_users SET follow=1 WHERE message_id=%d AND user_login_id=%d',array($row['id'],$row['user_login_id']));

			$mail = Base_User_SettingsCommon::get('Utils_Messenger','mail');
			if($mail) {
				$msg = __('Alert on: %s',array(Base_RegionalSettingsCommon::time2reg($row['alert_on'],2)))."\n".$fret."\n".($row['message']?__('Alarm comment: %s',array($row['message'])):'');
				Base_MailCommon::send($mail,'Alert!',$msg);
				$ret .= $mail.' => <pre>'.$msg.'</pre><br>';
			}
			Acl::set_user();
		}
		
		return $ret;
	}

    public static function menu() {
		if (Base_AclCommon::check_permission('Messenger Alerts'))
			return array(_M('Messenger alerts')=>array(
				'__function__'=>'browse'));
		return array();
	}
	
	public static function turn_off($id) {
	    DB::Execute('UPDATE utils_messenger_users SET done=1,done_on=%T WHERE user_login_id=%d AND message_id=%d',array(time(),Acl::get_user(),$id));
	}
}

eval_js_once('utils_messenger_on = true; utils_messenger_refresh = function(){'.
			'if(utils_messenger_on) new Ajax.Request(\'modules/Utils/Messenger/refresh.php\',{method:\'get\'});'.
			'};setInterval(\'utils_messenger_refresh()\',180000);utils_messenger_refresh()');

?>