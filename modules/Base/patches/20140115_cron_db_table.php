<?php
$ret = DB::CreateTable('cron',"func C(32) KEY,last I NOTNULL, running I1 NOTNULL DEFAULT 0");
if($ret===false)
	die('Invalid SQL query - Setup cron (cron table)');

