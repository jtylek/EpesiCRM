<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('DROP TABLE IF EXISTS base_notify');

DB::CreateTable('base_notify','
			token C(32) NOTNULL PRIMARY KEY,
			cache X,
			last_refresh I8,
			single_cache_uid I,
			telegram I1 DEFAULT 0',array('constraints' => ', FOREIGN KEY (singe_cache_uid) REFERENCES user_login(id)'));

