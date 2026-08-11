<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 */
ob_start();
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past


if(!isset($_POST['url']) || !isset($_SERVER['HTTP_X_CLIENT_ID']))
	die('alert(\'Invalid request\');');


define('JS_OUTPUT',1);
define('EPESI_PROCESS',1);
require_once('include.php');

if (epesi_requires_update()) {
    die ('window.location = "index.php";');
}
if(!isset($_SESSION['num_of_clients'])) {
	// Epesi.confirmLeave.freeze() (run by e.g. a Save button's onClick, right before
	// this very request was sent) only touches the internal forms/forms_freezed
	// bookkeeping - it does NOT clear the 'changed-input' class already applied to
	// edited fields. The forced reload below triggers a real browser navigation, so
	// the window's beforeunload handler (epesi.js's confirmLeave.activate()) still
	// sees those classes and throws up a native "leave site?" prompt, silently
	// swallowing this recovery reload - the user is left stuck on the (now
	// unsavable) form with no visible explanation. Unbind it first: by the time we
	// get here the session backing this tab is already gone, so there is nothing
	// left to protect by keeping the prompt.
	Epesi::js('jQuery(window).unbind(\'beforeunload\');');
	Epesi::alert('Session expired, restarting '.EPESI);
	Epesi::redirect();
	Epesi::send_output();
	define('SESSION_EXPIRED',1);
} elseif((!isset($_POST['history']) || !is_numeric($_POST['history']) || $_POST['history']>0) && !isset($_SESSION['client']['__history_id__'])) {
	// Same beforeunload trap as above - see comment there. Reachable on its own
	// (session otherwise intact) whenever this tab's client generation has aged out
	// of init_js.php's rolling window, e.g. after Android Chrome silently reloads a
	// backgrounded tab a few times.
	Epesi::js('jQuery(window).unbind(\'beforeunload\');');
	Epesi::alert('Too many tabs open - session expired, restarting '.EPESI);
	Epesi::redirect();
	Epesi::send_output();
	define('SESSION_EXPIRED',1);
	EpesiSession::destroy_client(session_id(),CID);
} else {
	Epesi::process($_POST['url'],$_POST['history'] ?? false);
}
$content = ob_get_contents();
ob_end_clean();

require_once('libs/minify/HTTP/Encoder.php');
$he = new HTTP_Encoder(array('content' => $content));
if (MINIFY_ENCODE)
	$he->encode();
$he->sendAll();
?>
