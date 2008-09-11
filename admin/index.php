<?php
require_once('auth.php');
/*
 * Ok, you are in.
 */
if(isset($_GET['mod'])) {
	call_user_func($_GET['mod']);
} else {
	print('<a href="modules.php">Modules administration</a><br>');
	print('<a href="modulesup.php">Update load priority array</a><br>');
	print('<a href="themeup.php">Update default theme</a><br>');
	print('<a href="langup.php">Update translations</a><br>');
	print('<a href="wfb.php">WFB File manager</a><br>');
	print('<a href="phpfm.php">phpfm File manager</a><br>');
	print('<a href="phpminiadmin.php">Mini MySQL Admin</a><br>');
	print('<hr>');
	print('<a href="phpinfo.php">PHP info</a><br>');
	print('<a href="dbinfo.php">database info</a><br>');
	print('<a href="configinfo.php">config info</a><br>');
	print('<hr>');
	print('<a href="logout.php">logout</a><br>');
}

?>
