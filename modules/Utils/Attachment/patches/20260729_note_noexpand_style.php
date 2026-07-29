<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// display_note() now renders title<br>body for the browse/mini-view preview;
// tag the field so RecordBrowser_0.php's grid rendering exempts it from the
// generic single-line "expandable row" collapse (see the 'style'=>'noexpand'
// comment in AttachmentInstall.php) instead of clipping the body as soon as
// the title's <br> pushes the cell past one line. Kept as its own patch file
// (not folded into 20260729_edited_on_noexpand_style.php, already applied on
// this install) - patches are identified by md5(filepath), not content, so
// editing an already-applied patch's body is silently ignored.
DB::Execute('UPDATE utils_attachment_field SET style=%s WHERE field=%s', array('noexpand', 'Note'));
