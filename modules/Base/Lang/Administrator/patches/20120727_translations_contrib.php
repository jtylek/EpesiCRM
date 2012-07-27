<?php

DB::CreateTable('base_lang_trans_contrib',
	'id I4 AUTO KEY,'.
	'user_id I4,'.
	'allow I1,'.
	'first_name C(64),'.
	'last_name C(64)',
	array());



?>
