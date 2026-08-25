<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// CRON_TOKEN used to be generate_token()'s md5(time() . getcwd()) - low
// entropy (narrow time-of-generation window + a often-guessable install
// path) already written to data/cron_token.php for every existing install.
// generate_token() now uses random_bytes() instead; regenerate the stored
// token so existing installs actually get the stronger value, not just
// fresh ones. Existing cron jobs / monitoring configs using the old URL
// will need updating with the new token from Administrator Panel->Cron.
Base_CronCommon::generate_token();
