<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */

/**
 * Define that this is valid access (thru ajax call).
 */
define("_VALID_ACCESS", true);
define("_SAJA_PROCESS",true);
//let browser know that response is utf-8 encoded
header('Content-Type: text/html; charset=utf-8');

require_once('include/include_path.php');
require_once "saja/saja.php";
require_once('include.php');


//initialize vars
$php = null;
$proc_file = null;
$function = null;
$session_id = null;
$request_id = null;
$errors = '';
$req = $_POST['req'];

if(!$req){
	$errors .= 'Empty Request';
}

if(!$errors){
	//start session and set ID to the expected saja session
	list($php, $session_id, $request_id) = explode('<!SAJA!>', $req);
	if($session_id)
		session_id($session_id);
	session_start();
	
	//file_put_contents('grr.txt',file_get_contents('grr.txt')."\n".$request_id.' in '.implode(', ',array_keys($_SESSION['SAJA_PROCESS']['REQUESTS'])).' for '.$req."\n");
	//validate this request
	if(!isset($_SESSION['SAJA_PROCESS']['REQUESTS']) || !is_array($_SESSION['SAJA_PROCESS']['REQUESTS']))
		$errors .=  'Session expired.';
	else if(!array_key_exists($request_id, $_SESSION['SAJA_PROCESS']['REQUESTS']))
		$errors .= 'Invalid Request.';
}


//start capturing the response
ob_start(array('ErrorHandler','handle_fatal'));

if(!$errors) {
	//get function name and process file
	$REQ = $_SESSION['SAJA_PROCESS']['REQUESTS'][$request_id];
	$proc_file = $REQ['PROCESS_FILE'];
	$function = $REQ['FUNCTION'];
	
	
	global $base;

	if($proc_file=='base.php') {
		require($proc_file);
		$base = new Base();
	} else {
		if(file_exists($proc_file))
			require($proc_file);
		else {
			$s = new Saja();
			$s->alert('Process file: ' . addslashes($proc_file) . ' not found.');
			echo $s->send();
			exit();
		}
		$base = new myFunctions();
	}

	$base->set_process_file($proc_file);
	$base->runFunc($function, $php);
	$base->call_jses();
	if($base->hasActions())
		echo $base->send();
} else {
	$s = new Saja();
	if(!isset($_SESSION['__last_error__']) || $_SESSION['__last_error__']!==$errors) {
		$s->alert($errors);
		$s->redirect('index.php');
		$_SESSION['__last_error__']=$errors;
	} else {
		$s->text('Fatal error - '.$errors,'error_box');
	}
	echo $s->send();
	exit();
}
$_SESSION['__last_error__']='';

//capture the response and output as utf-8 encoded
$content = ob_get_contents();
ob_end_clean();

if(GZIP_OUTPUT) {
	ob_start("ob_gzhandler");
	echo $content;
	ob_end_flush();
} else {
	echo $content;
}
?>
