<?php
/**
 * Keep epesi logged in.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-sessionkeeper
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_SessionKeeperCommon extends ModuleCommon {
	public static function user_settings(){
		return array('Session settings'=>array(
			array('name'=>'time','label'=>'Keep session at least','type'=>'select','values'=>array('default'=>'default server time','1800'=>'30 minutes','3600'=>'1 hour', '7200'=>'2 hours', '14400'=>'4 hours', '28800'=>'8 hours'),'default'=>'default','reload'=>true)
			));
	}

}
if(Acl::is_user()) {
	$time = Base_User_SettingsCommon::get('Apps/SessionKeeper','time');
	if($time!='default') {
		load_js('modules/Apps/SessionKeeper/sk.js');
		$sys_time = ini_get("session.gc_maxlifetime");
		$x_time = $time-$sys_time;
		if($x_time>0)
			eval_js_once('SessionKeeper.maxtime='.$x_time.';'.
				'SessionKeeper.interval='.($sys_time/2).';'.
				'SessionKeeper.load()');
	}
}
?>
