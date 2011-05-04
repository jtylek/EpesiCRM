<?php
require_once('auth.php');
print('<span style="font-family: courier; font-size: 11px;">');

error_log('Langup started on '.date('Y-m-d H:i:s').' (admin site) by user with id '.Acl::get_user()."\n", 3, 'data/langup.log');

$data_dir = DATA_DIR.'/Base_Lang/base/';
$content = scandir($data_dir);
foreach ($content as $name){
	if ($name == '.' || $name == '..') continue;
	$dot = strpos($name,'.');
	if (strtolower(substr($name,$dot+1))!='php') continue;
	$langcode = substr($name,0,$dot);
	if (!$langcode) continue;
	rename($data_dir.$name, $data_dir.$name.'.backup.'.date('Y_m_d__h_i_s'));
}

$ret = DB::Execute('SELECT * FROM modules');
while($row = $ret->FetchRow()) {
	$mod_name = $row[0];
	if ($mod_name=='Base') continue;
	if ($mod_name=='Tests') continue;
	global $translations;
	$directory = 'modules/'.str_replace('_','/',$mod_name).'/lang';
	if (!is_dir($directory)) continue;
	$content = scandir($directory);
	$trans_backup = $translations;
	foreach ($content as $name){
		if($name == '.' || $name == '..' || preg_match('/^[\.~]/',$name)) continue;
		$dot = strpos($name,'.');
		$langcode = substr($name,0,$dot);
		if (strtolower(substr($name,$dot+1))!='php') continue;
		$translations = array();
		@include(DATA_DIR.'/Base_Lang/base/'.$langcode.'.php');
		include($directory.'/'.$name);
		Base_LangCommon::save($langcode);
	}
	$translations = $trans_backup;
}

print('Finished.</span>');
print('<hr><a href="index.php">back</a>');
?>