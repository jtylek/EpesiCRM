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
if (php_sapi_name() == 'cli') {
    define('EPESI_DIR', '/');
    if (isset($argv[1])) {
        define('DATA_DIR', $argv[1]);
    }
} elseif (!isset($_GET['token'])) {
    die('Missing token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
} else {
    defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);
    require_once('include/include_path.php');
    require_once('include/data_dir.php');
    if(!file_exists(DATA_DIR.'/cron_token.php'))
        die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
    require_once(DATA_DIR.'/cron_token.php');
    if(CRON_TOKEN!=$_GET['token'])
        die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
}
require_once('include.php');

if (file_exists(DATA_DIR . '/maintenance_mode.php')) die();

$lock = DATA_DIR.'/cron.lock';
if(file_exists($lock) && filemtime($lock)>time()-6*3600) die();
register_shutdown_function(create_function('','@unlink("'.$lock.'");'));
file_put_contents($lock,'');

set_time_limit(0);
ini_set('memory_limit', '512M');
ModuleManager::load_modules();
Base_AclCommon::set_sa_user();
$ret = ModuleManager::call_common_methods('cron');
$cron_last = DB::GetAssoc('SELECT func,last,running FROM cron ORDER BY last');
$cron_funcs_prior = array(); //array of outdated cron callbacks
$t = time();
foreach($ret as $name=>$obj) {
    if(!$obj) continue;
    if(is_array($obj)) {
        foreach($obj as $func=>$every) {
           if(!strpos($func,'::')) $func = $name.'Common::'.$func;
           $func_md5 = md5($func);
           //if first cron run exists and it was executed in specified time or it keep running less then 24h - skip
           if(isset($cron_last[$func_md5]) && ($cron_last[$func_md5]['last']>$t-$every*60 || ($cron_last[$func_md5]['running'] && $cron_last[$func_md5]['last']>$t-min($every*60*30,24*60*60)))) continue;
           if(!isset($cron_last[$func_md5])) {
               DB::Execute('INSERT INTO cron(func,last,running,description) VALUES (%s,%d,%b,%s)',array($func_md5,0,0,$func));
               $cron_last = array_merge(array($func_md5 => array('last'=>0,'running'=>0)),$cron_last);
           }
           $cron_funcs_prior[$func_md5] = $func;
        }
    }
}

//print_r($cron_last);
//print_r($cron_funcs_prior);

function adodb_error() {}
class CronErrorObserver extends ErrorObserver {
    private $func_md5;
    public function __construct($func_md5) {
        $this->func_md5 = $func_md5;
    }
    
    public function update_observer($type, $message, $errfile, $errline, $errcontext, $backtrace) {
        global $cron_funcs_prior;
        $backtrace = htmlspecialchars_decode(str_replace(array('<br />','&nbsp;'),array("\n",' '),$backtrace));
        $x = $cron_funcs_prior[$this->func_md5].":\ntype=".$type."\nmessage=".$message."\nerror file=".$errfile."\nerror line=".$errline."\n".$backtrace;
        epesi_log($x."\n", 'cron.log');
        
        DB::IgnoreErrors(array('adodb_error',null)); //ignore adodb errors
        $query_args = array(time(),$this->func_md5);
        $query = DB::TypeControl('UPDATE cron SET last=%d,running=0 WHERE func=%s',$query_args);
        if(!DB::Execute($query,$query_args)) { //if not - probably server gone away - retry every 10 seconds for 1h
            for($i=0; $i<360; $i++) {
                sleep(10);
                $connection = null;
                try {
                    $connection = DB::Connect(); //reconnect database as new connection
                } catch(Exception $e) {
                    continue; //no connection - wait
                }
                if($connection->Execute($query,$query_args)) {  //if ok then break and exit
                    $connection->Close();
                    break;
                }
                $connection->Close();
            }
        }

        return true;
    }
}

//call oldest executed callback
foreach($cron_last as $func_md5=>$last) {
    if(!isset($cron_funcs_prior[$func_md5])) continue;
    
    DB::Execute('UPDATE cron SET last=%d,running=1 WHERE func=%s',array($t,$func_md5));
    @unlink($lock);

//	print('call '.$cron_funcs_prior[$func_md5]."\n");
    $error_handler = new CronErrorObserver($func_md5);
    ErrorHandler::add_observer($error_handler);
    ob_start();
    $output = array();
    try {
        $output[0] = call_user_func(explode('::',$cron_funcs_prior[$func_md5]));
    } catch(Exception $e) {
        $output[0] = 'Cron Exception: '.$e->getMessage();
    }
    $output[1] = ob_get_clean();
    $output = array_filter($output);
    if($output) {
        $output = implode("<br />\n",$output);
        $stripped = $cron_funcs_prior[$func_md5].":\n".strip_tags($output)."\n\n";
        if(isset($argv))
            print($stripped);
        else
            print($cron_funcs_prior[$func_md5].":<br>".$output."<hr>");
        epesi_log($stripped, 'cron.log');
    }

    DB::Execute('UPDATE cron SET last=%d,running=0 WHERE func=%s',array(time(),$func_md5));
    break;
}
@unlink($lock);

