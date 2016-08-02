<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE 
				base_user_settings 
			SET 
				module=%s 
			WHERE 
				module=%s 
				AND 
				variable LIKE "%_filters"', array('Utils_RecordBrowser_Filters', 'Utils_RecordBrowser'));
