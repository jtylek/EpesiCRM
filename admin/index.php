<?php
require_once('auth.php');
/*
 * Ok, you are in.
 */

require_once('functions.php');
pageheader();
starttable();
if(isset($_GET['mod'])) {
	call_user_func($_GET['mod']);
} else {
	print('<a href="modules.php">Uninstall modules</a><br>');
	print('<a href="modulesup.php">Update load priority array</a><br>');
	print('<a href="themeup.php">Update default theme</a><br>');
	print('<a href="langup.php">Update translations</a><br>');
	print('<a href="wfb.php" TARGET=_BLANK>WFB File manager</a>&nbsp;&nbsp;(Opens new window)<br>');
	print('<a href="phpfm.php" TARGET=_BLANK>phpfm File manager</a>&nbsp;&nbsp;(Opens new window)<br>');
	print('<a href="phpminiadmin.php">Mini MySQL Admin</a><br>');
	print('<hr>');
	print('<a href="phpinfo.php">PHP info</a><br>');
	print('<a href="configinfo.php">PHP environment & config.php</a><br>');
	print('<hr>');
	print('<a href="logout.php">logout</a><br>');
}
closetable();
pagefooter();

//session_write();
?>
