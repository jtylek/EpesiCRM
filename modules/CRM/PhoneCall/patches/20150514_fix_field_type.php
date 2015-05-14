<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE phonecall_field SET type=%s WHERE field=%s AND type=%s', array('text', 'Customer', 'select'));
