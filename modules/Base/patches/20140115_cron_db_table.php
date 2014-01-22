<?php
$ret = DB::CreateTable('cron',"func C(32) KEY,last I NOTNULL, running I1 NOTNULL DEFAULT 0");
if($ret===false) {
    $msg = 'Can\'t create cron table which is necessary to run EPESI.';
	throw new ErrorException($msg);
}
