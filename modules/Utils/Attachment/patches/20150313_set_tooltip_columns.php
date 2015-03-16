<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$sql = 'UPDATE utils_attachment_field SET tooltip=%d WHERE field=%s';
DB::Execute($sql, array(1, 'Title'));
DB::Execute($sql, array(1, 'Permission'));
DB::Execute($sql, array(1, 'Attached to'));
DB::Execute($sql, array(1, 'Crypted'));
DB::Execute($sql, array(1, 'Sticky'));

DB::Execute($sql, array(0, 'Note'));
DB::Execute($sql, array(0, 'Edited on'));
