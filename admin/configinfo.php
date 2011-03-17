<?php
require_once('auth.php');
/*
 * Ok, you are in.
 */

require_once('functions.php');
pageheader();

print('<CENTER><div class="header">PHP environment check</div></CENTER>');
starttable();

if(is_writable('data')){
	echo '<div class="left">Directory is writeable</div><div class="right"><strong class="green">OK</strong></div><br />';
} else {
	die('<strong class="red">WARNING ! </strong>This directory is not writeable. Please fix privileges.');
}

if(version_compare(phpversion(), '5.1.0')==-1){
	die('<strong class="red">WARNING ! </strong>You are running an old version of PHP, minimum version 5.1 required.');
} else {
	echo '<div class="left">PHP version: ' . phpversion() . '</div><div class="right"><strong class="green">OK</strong></div><br />';
}

if (extension_loaded('curl')) { // Test if curl is loaded
  echo '<div class="left">Curl Loaded</div><div class="right"><strong class="green">OK</strong></div><br />';
	} else {
  die ('<B>WARNING !</B> Curl extension not found - Please uncomment <B>;extension=php_curl.dll</B> line in your php.ini');
	}

closetable();
print('<CENTER><div class="header">epesi config.php</div></CENTER>');
starttable();

printTD('epesi version:',EPESI_VERSION);
printTD('epesi revison:',EPESI_REVISION);
printTD('Database Host:',DATABASE_HOST);
printTD('Database User:',DATABASE_USER);
printTD('Database Password:',DATABASE_PASSWORD);
printTD('Database Name:',DATABASE_NAME);
printTD('Database Driver:',DATABASE_DRIVER);
printTD('epesi Local Dir:',EPESI_LOCAL_DIR);
printTD('epesi Dir:',EPESI_DIR);
printTD('System Timezone:',SYSTEM_TIMEZONE);

printTD('Debug:',(DEBUG?'YES':'NO'));
printTD('Module Times:',(MODULE_TIMES?'YES':'NO'));
printTD('Display sql queries processing times: ',(SQL_TIMES?'YES':'NO'));
printTD('Strip output html from comments: ',(STRIP_OUTPUT?'YES':'NO'));
printTD('Display additional error info: ',(DISPLAY_ERRORS?'YES':'NO'));
printTD('Report all errors (E_ALL): ',(REPORT_ALL_ERRORS?'YES':'NO'));
printTD('GZIP client web browser history: ',(GZIP_HISTORY?'YES':'NO'));

printTD('Reducing Transfer: ',(REDUCING_TRANSFER?'YES':'NO'));
printTD('Cache Common Files: ',(CACHE_COMMON_FILES?'YES':'NO'));
printTD('Minify Encode: ',(MINIFY_ENCODE?'YES':'NO'));
printTD('Suggest Donation: ',(SUGGEST_DONATION?'YES':'NO'));
printTD('Check epesi version: ',(CHECK_VERSION?'YES':'NO'));
printTD('JS Output: ',(JS_OUTPUT?'YES':'NO'));
printTD('Set Session: ',(SET_SESSION?'YES':'NO'));

printTD('Read Only Session: ',(READ_ONLY_SESSION?'YES':'NO'));
printTD('Mobile Device: ',(MOBILE_DEVICE?'YES':'NO'));
printTD('First Run: ',(FIRST_RUN?'YES':'NO'));
printTD('Trial Mode: ',(TRIAL_MODE?'YES':'NO'));
printTD('Demo Mode: ',(DEMO_MODE?'YES':'NO'));

closetable();
pagefooter();
?>