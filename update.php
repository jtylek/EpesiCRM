<?php
/**
 * Epesi core updater.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

include_once('include/misc.php');

function themeup(){
	$data_dir = 'data/Base_Theme/templates/default/';
	$content = scandir($data_dir);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		if (!is_dir($data_dir.'/'.$name)){
			unlink($data_dir.'/'.$name);
		}
	}	
	$ret = DB::Execute('SELECT * FROM modules');
	while($row = $ret->FetchRow()) {
		$directory = 'modules/'.str_replace('_','/',$row[0]).'/theme_'.$row['version'];
		if (!is_dir($directory)) $directory = 'modules/'.str_replace('_','/',$row[0]).'/theme';
		$mod_name = $row[0];
		$data_dir = 'data/Base_Theme/templates/default/';
		if (!is_dir($directory)) continue;
		$content = scandir($directory);
		foreach ($content as $name){
			if($name == '.' || $name == '..' || strpos('.php',$name)!==false) continue;
			if (!is_dir($directory.'/'.$name)){
				copy($directory.'/'.$name,$data_dir.$mod_name.'__'.$name);
			}
		}
	}
}

$versions = array('0.8.5','0.8.6','0.8.7','0.8.8','0.8.9','0.8.10','0.8.11','0.9.0');

/******************* 0.8.11 to 0.9.0 **********************/
function mod_cmp($a, $b){
	return strlen($b['name']) - strlen($a['name']);
}
function update_from_0_8_11_to_0_9_0() {
	DB::Execute('UPDATE modules SET version=0 WHERE name=\'Base_Admin\'');
	$tmp_dir = rtrim(sys_get_temp_dir(),'\\/');
	recursive_copy('data',$tmp_dir.'data_old');
	unlink($tmp_dir.'data_old/config.php');
	recursive_copy('data',$tmp_dir.'data_tmp');
	unlink($tmp_dir.'data_tmp/config.php');
	$content = scandir('data/');
	foreach($content as $name) {
		if($name == '.' || $name == '..' || $name == 'config.php') continue;
		recursive_rmdir('data/'.$name);
	}
	$mod = DB::GetAll('SELECT name FROM modules');
	usort($mod,'mod_cmp');
	foreach($mod as $row) {
		$name = str_replace('_','/',$row['name']);
		recursive_copy($tmp_dir.'data_tmp/'.$name,'data/'.$row['name']);
		recursive_rmdir($tmp_dir.'data_tmp/'.$name);
	}
	recursive_rmdir($tmp_dir.'data_tmp');
	themeup();
}
/****************** 0.8.6 to 0.8.7 **********************/
function update_from_0_8_6_to_0_8_7() {
}
/****************** 0.8.5 to 0.8.6 **********************/
function update_from_0_8_5_to_0_8_6() {
}

//=========================================================================

try {
$cur_ver = Variable::get('version');
} catch(Exception $s) {
$cur_ver = '0.8.5';
}
$go=false;
$last_ver = '';
foreach($versions as $v) {
	$x = str_replace('.','_',$v);
	if($go) {
		if(is_callable('update_from_'.$last_ver.'_to_'.$x)) {
//			print('Update from '.$last_ver.' to '.$x.'<br>');
			call_user_func('update_from_'.$last_ver.'_to_'.$x);
		}
	}
	if($v==$cur_ver) $go=true;
	if($v==EPESI_VERSION) $go=false;
	$last_ver = $x;
}
Variable::set('version',EPESI_VERSION);
?>
