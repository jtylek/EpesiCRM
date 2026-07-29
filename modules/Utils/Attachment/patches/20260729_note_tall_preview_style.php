<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// The 'Note' field was tagged 'noexpand' (patches/20260729_note_noexpand_style.php,
// already applied on this install) alongside 'Edited on' to stop the generic
// 18px row collapse from cutting its body off right after the title. That
// fully disabled collapsing for this column too, though - unlike 'Edited on'
// (always exactly 3 fixed lines), this field is open-ended user text and
// should still support a compact/collapsed preview, just with a taller
// collapsed height (see the matching CSS in
// GenericBrowser/theme_adminlte/default.css) instead of none at all.
DB::Execute('UPDATE utils_attachment_field SET style=%s WHERE field=%s', array('tall_preview', 'Note'));
