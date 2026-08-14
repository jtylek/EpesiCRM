<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$ret = DB::CreateTable('utils_currency_rate',
			'id I AUTO KEY,'.
			'currency_id I NOTNULL,'.
			'target_currency_id I NOTNULL,'.
			'rate_date D NOTNULL,'.
			'rate F NOTNULL,'.
			'source C(32),'.
			'fetched I8 NOTNULL',
			array('constraints'=>''));
if($ret===false) {
	throw new ErrorException('Can\'t create utils_currency_rate table which is necessary for the currency exchange rate cache.');
}
DB::CreateIndex('utils_currency_rate_pair', 'utils_currency_rate', 'currency_id,target_currency_id,rate_date', array('UNIQUE'=>1));
