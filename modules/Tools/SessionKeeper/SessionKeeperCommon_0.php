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
        $def = array('default'=>__('default server time (%s minutes)', array($time/60)));
        if($time<1800)
            $def['1800']=__('30 minutes');
        if($time<3600)
            $def['3600']=__('1 hour');
        if($time<7200)
            $def['7200']=__('2 hours');
        if($time<14400)
            $def['14400']=__('4 hours');
        if($time<28800)
            $def['28800']=__('8 hours');
        return array(__('Misc')=>array(
            array('name'=>'time','label'=>__('Keep session at least'),'type'=>'select','values'=>$def,'default'=>28800,'reload'=>true)
            ));
    }

}

load_js('modules/Tools/SessionKeeper/sk.js');
$sys_time = ini_get("session.gc_maxlifetime");
$interval = $sys_time/3;

if(Acl::is_user()) {
    $time = Base_User_SettingsCommon::get('Tools/SessionKeeper','time');
    if($time=='default')
        $time = $sys_time;
    eval_js_once('SessionKeeper.maxtime='.$time.';'.
            'SessionKeeper.interval='.$interval.';'.
            'SessionKeeper.load()');
} else {
    eval_js_once('SessionKeeper.maxtime=201600;'. //one week
            'SessionKeeper.interval='.$interval.';'.
            'SessionKeeper.load()');

}
?>
