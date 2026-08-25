<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::CreateTable('utils_watchdog_notification_queue', 'event_id I KEY');
