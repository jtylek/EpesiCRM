<?php
header("Content-type: text/javascript");
define('JS_OUTPUT',1);
define('CID',false); //don't load user session
require_once('../../../../../include.php');
session_commit();


if(!Acl::is_user()) return;
$user = DB::GetOne('SELECT id FROM user_login WHERE login=%s',array(Acl::get_user()));

parse_str($_POST['data'], $x);

foreach($x as $pos=>$id) {
	$event = split('_', $pos);
	//DB::Execute('insert into testowa values(%s, %s)',array($pos, $id));
	if($event[1]) {
		$str = split('X', $event[1]);
		$str2 = split('_', $id);
		$ev_id = $str[1];
		
		$old_pos = $str[0];
		$str = split('_', $pos);
		$ev_new_start = $str2[1];
		if(substr($ev_new_start, 8, 2) == 'tt') {
			$ev_new_end = substr($ev_new_start, 0, 4).'-'.substr($ev_new_start, 4, 2).'-'.substr($ev_new_start, 6, 2).' 00:00';
			$ev_new_start = substr($ev_new_start, 0, 4).'-'.substr($ev_new_start, 4, 2).'-'.substr($ev_new_start, 6, 2).' 00:00';
			DB::Execute('update calendar_event_personal set datetime_start=%T, datetime_end=%T, timeless=1 where id=%d',array($ev_new_start, $ev_new_end, $ev_id));	
		} else {
			substr($old_pos, 10, 2) == 'tt' ? $min = '00' : $min = substr($old_pos, 10, 2);
			$ev_new_start = substr($ev_new_start, 0, 4).'-'.substr($ev_new_start, 4, 2).'-'.substr($ev_new_start, 6, 2).' '.substr($ev_new_start, 8, 2). ':'.$min;
			//DB::Execute('insert into t2(a) values(%s)',array($ev_new_start));
			DB::Execute(
				'update calendar_event_personal '.
				' set '.
					'datetime_end=addtime(datetime_end, timediff(%T, datetime_start)), '.
					'datetime_start=%T, '.
					'timeless=0 '.
				'where id=%d',
				array($ev_new_start, $ev_new_start, $ev_id)
			);
		}
		
	
	}
	/*$str = split('-', $id[0]['id']);
	$old_pos = $str[0];
	$ev_id = $str[1];
	$str = split('_', $pos);
	$ev_new_start = $str[1];
	$ev_new_start = substr($ev_new_start, 0, 4).'-'.substr($ev_new_start, 4, 2).'-'.substr($ev_new_start, 6, 2).' '.substr($ev_new_start, 8, 2). ':00';
	//$str = split('-', $pos);
*/
	//DB::Execute('update calendar_event_personal set datetime_start=%d where id=%d',array($ev_new_start, $ev_id));
	//
	
}
?>
