<?php
/**
 * Keeps epesi user logged in.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tools
 * @subpackage SessionKeeper
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_SessionKeeperCommon extends ModuleCommon {
	public static function user_settings(){
		$time = ini_get("session.gc_maxlifetime");
		$def = array('default'=>'default server time ('.($time/60).' minutes)');
		if($time<1800)
			$def['1800']='30 minutes';
		if($time<3600)
			$def['3600']='1 hour';
		if($time<7200)
			$def['7200']='2 hours';
		if($time<14400)
			$def['14400']='4 hours';
		if($time<28800)
			$def['28800']='8 hours';
		return array('Misc'=>array(
			array('name'=>'time','label'=>'Keep session at least','type'=>'select','values'=>$def,'default'=>'default','reload'=>true)
			));
	}

}
if(Acl::is_user()) {
	$time = Base_User_SettingsCommon::get('Tools/SessionKeeper','time');
	if($time!='default') {
		load_js('modules/Tools/SessionKeeper/sk.js');
		$sys_time = ini_get("session.gc_maxlifetime");
		$x_time = $time-$sys_time;
		$interval = $sys_time/2;
		if($x_time<$interval)
			$interval = $x_time;
		eval_js_once('SessionKeeper.maxtime='.$x_time.';'.
			'SessionKeeper.interval='.$interval.';'.
			'SessionKeeper.load()');
	}
}
?>
