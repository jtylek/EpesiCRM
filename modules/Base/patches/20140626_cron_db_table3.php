<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('DELETE FROM cron WHERE description is NULL');
