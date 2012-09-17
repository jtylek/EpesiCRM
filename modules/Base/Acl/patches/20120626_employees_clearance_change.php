<?php

Utils_CommonDataCommon::extend_array('Contacts/Access',array('employee'=>_M('Employee')));

$cmp = Variable::get('main_company', null);
if ($cmp!==null) {
	set_time_limit(0);

	$conts = DB::GetAll('SELECT * FROM contact_data_1 WHERE f_company_name=%d OR f_related_companies '.DB::like().' '.DB::Concat(DB::qstr('\_\_'), DB::qstr($cmp), DB::qstr('\_\_')), array($cmp));
	foreach ($conts as $k=>$v) {
		$v['access'] = Utils_RecordBrowserCommon::decode_multi($v['f_access']);
		$v['access'][] = 'employee';
		Utils_RecordBrowserCommon::update_record('contact', $v['id'], array('access'=>$v['access']));
	}

	Variable::delete('main_company', false);
}

$tab = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
foreach ($tab as $t) {
	DB::Execute('UPDATE '.$t.'_access_clearance SET clearance=%s WHERE clearance=%s', array('ACCESS:employee', 'EMPLOYEE'));
}

?>
