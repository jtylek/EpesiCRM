<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// display_date() now renders 3 lines (date/time/user); tag the field so
// RecordBrowser_0.php's grid rendering exempts it from the generic
// single-line "expandable row" collapse (see the 'style'=>'noexpand' comment
// in AttachmentInstall.php) instead of clipping it to line 1 whenever any
// cell in the row is tall enough to trigger that collapse.
DB::Execute('UPDATE utils_attachment_field SET style=%s WHERE field=%s', array('noexpand', 'Edited on'));
