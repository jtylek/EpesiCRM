<?php
/**
 * Define that this is valid access (thru ajax call).
 */
define("_VALID_ACCESS", true);

require_once('include/include_path.php');
require_once('include/config.php');
require_once('include/session.php');

//let browser know that response is utf-8 encoded
header('Content-Type: text/html; charset=utf-8');

//initialize vars
$php = null;
$proc_file = null;
$function = null;
$session_id = null;
$request_id = null;
$true_utf8 = null;
$errors = '';
file_put_contents('/tmp/dupa',print_r($_POST,true));
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
	
	//validate this request
	if(!is_array($_SESSION['SAJA_PROCESS']['REQUESTS']))
		$errors .=  'Session expired.';
	else if(!in_array($request_id, array_keys($_SESSION['SAJA_PROCESS']['REQUESTS'])))
		$errors .= 'Invalid Request.';
}

require_once "saja/saja.php";

//start capturing the response
ob_start();

if(!$errors) {

	//get function name and process file
	$REQ = $_SESSION['SAJA_PROCESS']['REQUESTS'][$request_id];
	$proc_file = $REQ['PROCESS_FILE'];
	$function = $REQ['FUNCTION'];
	$true_utf8 = $REQ['UTF8'];
	
	
	global $base;

	require_once('include.php');
	if($proc_file=='base.php') {
		require($proc_file);
		$base = new Base(true);
	} else {
		if(file_exists($proc_file))
			require($proc_file);
		else {
			$s = new saja(true);
			$s->alert('Process file: ' . addslashes($proc_file) . ' not found.');
			echo $s->send();
			exit();
		}
		$base = new myFunctions(true);
	}

	$base->set_process_file($proc_file);
	ob_start(array('ErrorHandler','handle_fatal'));
	$base->set_true_utf8($true_utf8);
	$base->runFunc($function, $php);
	$base->call_jses();
	ob_end_flush();
	if($base->hasActions())
		echo $base->send();
} else {
	$s = new saja(true);
	$s->alert($errors);
	echo $s->send();
	exit();
}

//capture the response and output as utf-8 encoded
$content = ob_get_contents();
ob_end_clean();

if($s->true_utf8)
	echo $content;
else
	echo utf8_encode($content);
?>
