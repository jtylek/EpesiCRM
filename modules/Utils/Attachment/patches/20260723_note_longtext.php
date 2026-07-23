<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// f_note was TEXT (64KB limit), silently truncating notes with multiple
// pasted clipboard images (base64-encoded, easily >64KB combined) since
// sql_mode lacks STRICT_TRANS_TABLES.
$dict = DB::dict();
$sqlarray = $dict->alterColumnSQL('utils_attachment_data_1', 'f_note '.$dict->ActualType('XL'));
$dict->executeSQLArray($sqlarray);
