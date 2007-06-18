<?php
/**
 * Define that this is valid access (thru ajax call).
 */
define("_VALID_ACCESS", true);

//initialize vars
$php = null;
$proc_file = null;
$function = null;
$session_id = null;
$request_id = null;

//read the incoming request
$HTTP_RAW_POST_DATA = file_get_contents('php://input');
if(!$HTTP_RAW_POST_DATA) exit('Empty Request');

//start session and set ID to the expected saja session	
list($php, $session_id, $request_id) = explode('<!SAJA!>', $HTTP_RAW_POST_DATA);
if($session_id && $session_id != session_id())
	session_id($session_id);
session_start();

$delimiter = ($_ENV['OS']=='Windows_NT')?';':':';
ini_set('include_path','libs'.$delimiter.ini_get('include_path'));
require_once "saja/saja.php";

//validate this request
if(!is_array($_SESSION['SAJA_PROCESS']['REQUESTS'])) {
		$s = new saja(true);
		$s->js('alert(\'Invalid session (Saja not initialized).\')');
		$s->redirect();
		echo $s->send();
		exit();
}
if(!in_array($request_id, array_keys($_SESSION['SAJA_PROCESS']['REQUESTS'])))
	exit('Invalid Request: '.$request_id);

//get function name and process file
$proc_file = $_SESSION['SAJA_PROCESS']['REQUESTS'][$request_id]['PROCESS_FILE'];
$function = $_SESSION['SAJA_PROCESS']['REQUESTS'][$request_id]['FUNCTION'];

//load the class extension containing the user functions
if($proc_file=='base.php') {
	require($proc_file);
	global $base;
	$base = new Base(true);
} else {
	if(file_exists($proc_file))
		require($proc_file);
	else {
		$s = new saja(true);
		$s->text('Process file: ' . addslashes($proc_file) . ' not found.','error_box,p');
		echo $s->send();
		exit();
	}
	$base = new myFunctions(true);
}
$base->set_process_file('base.php');
$base->runFunc($function, $php);
if($base->hasActions())
	echo $base->send();
?>
