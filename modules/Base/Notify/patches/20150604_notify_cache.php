<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('DROP TABLE IF EXISTS base_notify');
		
DB::CreateTable('base_notify','
			user_id I4,
			token C(255),
			cache X,
			session_start T,
			last_refresh I8');