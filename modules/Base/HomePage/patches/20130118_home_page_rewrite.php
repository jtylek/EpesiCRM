<?php

@DB::DropTable('home_page');
@DB::CreateTable('base_home_page',
	'id I4 AUTO KEY,'.
	'priority I4,'.
	'home_page C(64)',
	array('constraints' => ''));
@DB::CreateTable('base_home_page_clearance',
	'id I4 AUTO KEY,'.
	'home_page_id I,'.
	'clearance C(64)',
	array('constraints' => ', FOREIGN KEY (home_page_id) REFERENCES base_home_page(id)'));

Base_HomePageCommon::set_home_page(_M('Dashboard'),array('ACCESS:employee'));
Base_HomePageCommon::set_home_page(_M('My Contact'),array());

?>
