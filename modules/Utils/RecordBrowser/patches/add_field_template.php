<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$checkpoint = Patch::checkpoint('tabs');
if($checkpoint->has('tabs'))
	$tabs = $checkpoint->get('tabs');
else
	$tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties');
	
foreach($tabs as $i=>$tab) {
	$checkpoint->require_time(1);
	@PatchUtil::db_add_column($tab.'_field', 'template', 'C(255)');
	
	unset($tabs[$i]);
	$checkpoint->set('tabs', $tabs);
}