<?php
if (!defined('_VALID_ACCESS'))
    define('_VALID_ACCESS',1);
require_once('include/data_dir.php');
if(!file_exists(DATA_DIR.'/config.php'))
	die();

if(!is_writable(DATA_DIR))
	die();

require_once('include/config.php');
require_once('include/error.php');
require_once('include/database.php');

if(defined('CID')) {
	if(constant('CID')!==false) die('alert(\'Invalid update script defined custom CID. Please try to refresh site manually.\');');
} else
	define('CID',false); //i know that i won't access $_SESSION['client']

require_once('include/session.php');

// if it's direct request to this file return content-type: text/javascript
// otherwise it's include and do not send header.
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
    header("Content-type: text/javascript");

$client_id = $_SESSION['num_of_clients'] ?? 0;
$client_id_next = $client_id+1;
$_SESSION['num_of_clients'] = $client_id_next;

//DBSession::destroy_client(session_id(),$client_id);
// Window used to be 5, evicting the session bucket of any tab still open once 5
// more index.php loads happened anywhere in this browser session (any tab, any
// reload) - process.php then bounces that tab's next action (e.g. a RecordBrowser
// Save) through the "too many tabs" recovery path, discarding whatever was being
// submitted. On mobile this is easy to hit with perfectly normal use: Android
// Chrome silently reloads backgrounded tabs under memory pressure, each reload
// counting as a new generation even with only one or two tabs actually open. 25
// keeps the same unbounded-growth protection while giving that far more headroom.
if($client_id-25>=0) {
    EpesiSession::destroy_client(session_id(),$client_id-25);
    $_SESSION['session_destroyed'][$client_id-25] = 1;
}
session_commit();

?>Epesi.init(<?php print($client_id); ?>,'<?php print(rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])),'/').'/process.php'); ?>','<?php print(http_build_query($_GET));?>');