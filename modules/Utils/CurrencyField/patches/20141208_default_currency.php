<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

@PatchUtil::db_add_column('utils_currency','default_currency','I1');
$first = DB::GetCol('SELECT id FROM utils_currency WHERE active=1 AND default_currency=1 ORDER BY id');
if(!$first) $first = DB::GetCol('SELECT id FROM utils_currency WHERE active=1 ORDER BY id');
DB::Execute('UPDATE utils_currency SET default_currency=0');
if($first) DB::Execute('UPDATE utils_currency SET default_currency=1 WHERE id=%d',array($first[0]));
