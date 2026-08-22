<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('DROP TABLE IF EXISTS base_notify');
		
DB::CreateTable('base_notify','
			token C(64) NOTNULL PRIMARY KEY,
			cache X,
			last_refresh I8');
