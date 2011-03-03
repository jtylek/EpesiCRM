<?php
require_once('functions.php');
require_once('include.php');

pageheader();
starttable();

require_once('auth.php');

if(isset($_GET['mod'])) {
	call_user_func($_GET['mod']);
} else {
	print('<a href="modules.php">1. Uninstall modules</a><br>');
	print('<a href="modulesup.php">2. Update load priority array</a><br>');
	print('<a href="themeup.php">3. Rebuild Common cache & default theme</a><br>');
	print('<a href="langup.php">4. Update translations</a><br>');
	print('<hr>');
	print('<a href="wfb.php" TARGET=_BLANK>5. WFB File manager</a>&nbsp;&nbsp;(Opens new window)<br>');
	print('<a href="phpfm.php" TARGET=_BLANK>6. phpfm File manager</a>&nbsp;&nbsp;(Opens new window)<br>');
	print('<a href="phpminiadmin.php">7. Mini MySQL Admin</a><br>');
	print('<hr>');
	print('<a href="phpinfo.php">8. PHP info</a><br>');
	print('<a href="configinfo.php">9. PHP environment & config.php</a><br>');
	print('<hr>');
	print('<a href="logout.php">logout</a><br>');
}
closetable();
pagefooter();

//session_write();
?>
