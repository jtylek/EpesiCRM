<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$versions = array('0.8.5','0.8.6','0.8.7');

function update_from_0_8_6_to_0_8_7() {
}

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
