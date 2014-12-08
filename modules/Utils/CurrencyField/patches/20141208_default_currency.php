<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

@PatchUtil::db_add_column('utils_currency','default','I1');
$first = DB::GetOne('SELECT id FROM utils_currency WHERE active=1 AND default=1 ORDER BY id');
if(!$first) $first = DB::GetOne('SELECT id FROM utils_currency WHERE active=1 ORDER BY id');
DB::Execute('UPDATE utils_currency SET default=0');
DB::Execute('UPDATE utils_currency SET default=1 WHERE id=%d',array($first));
