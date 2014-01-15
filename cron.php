<?php
/**
 * This file provides cron functionality... Add it to your cron.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 */
define('CID',false);
define('SET_SESSION',false);
if(isset($argv))
	define('EPESI_DIR','/');
require_once('include.php');

$lock = DATA_DIR.'/cron.lock';
if(file_exists($lock) && filemtime($lock)>time()-6*3600) die();
register_shutdown_function(create_function('','@unlink("'.$lock.'");'));
file_put_contents($lock,'');

set_time_limit(0);
ini_set('memory_limit', '512M');
ModuleManager::load_modules();
Base_AclCommon::set_user(1);
$ret = ModuleManager::call_common_methods('cron');
$cron_funcs = array();
foreach($ret as $name=>$obj) {
    if(!$obj) continue;
    if(is_array($obj)) {
        foreach($obj as $func=>$every) {
           if(!strpos($func,'::')) $func = $name.'Common::'.$func;
           $cron_funcs[$func] = $every;
        }
    }
}
arsort($cron_funcs);
$cron_last = DB::GetAssoc('SELECT func,last,running FROM cron ORDER BY last');
$cron_funcs_prior = array(); //array of outdated cron callbacks
$t = time();
foreach($cron_funcs as $func=>$every) {
	$func_md5 = md5($func);
	//if first cron run exists and it was executed in specified time or it keep running less then 24h - skip
    if(isset($cron_last[$func_md5]) && ($cron_last[$func_md5]['last']>$t-$every*60 || ($cron_last[$func_md5]['running'] && $cron_last[$func_md5]['last']>$t-24*60*60))) continue;
    if(!isset($cron_last[$func_md5])) {
        DB::Execute('INSERT INTO cron(func,last,running) VALUES (%s,%d,%b)',array($func_md5,0,0));
        $cron_last[$func_md5] = array('last'=>0,'running'=>0);
    }
    $cron_funcs_prior[$func_md5] = $func;
}

print_r($cron_last);
print_r($cron_funcs_prior);

//call oldest executed callback
foreach($cron_last as $func_md5=>$last) {
    if(!isset($cron_funcs_prior[$func_md5])) continue;
    
    DB::Execute('UPDATE cron SET last=%d,running=1 WHERE func=%s',array($t,$func_md5));
    @unlink($lock);

	print('call '.$cron_funcs_prior[$func_md5]."\n");
    $output = call_user_func(explode('::',$cron_funcs_prior[$func_md5]));
    if($output) {
        if(isset($argv))
            print($name.":\n".strip_tags($output)."\n\n");
        else
            print($name.":<br>".$output."<hr>");
    }

    DB::Execute('UPDATE cron SET last=%d,running=0 WHERE func=%s',array(time(),$func_md5));
    break;
}
@unlink($lock);
