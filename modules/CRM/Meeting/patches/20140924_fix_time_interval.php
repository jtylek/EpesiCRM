<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE crm_meeting_field SET param=%d WHERE field=%s AND param=%d', array(5, 'Time', 255));
