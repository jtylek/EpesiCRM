<?php
require_once('auth.php');
print('Debug: '.(DEBUG?'YES':'no').'<br>');
print('Display modules loading times: '.(MODULE_TIMES?'YES':'no').'<br>');
print('Display sql queries processing times: '.(SQL_TIMES?'YES':'no').'<br>');
print('Strip output html from comments: '.(STRIP_OUTPUT?'YES':'no').'<br>');
print('Display additional error info: '.(DISPLAY_ERRORS?'YES':'no').'<br>');
print('Report all errors (E_ALL): '.(REPORT_ALL_ERRORS?'YES':'no').'<br>');
print('GZIP client web browser history: '.(GZIP_HISTORY?'YES':'no').'<br>');
print('<hr><a href="index.php">back</a>');
?>