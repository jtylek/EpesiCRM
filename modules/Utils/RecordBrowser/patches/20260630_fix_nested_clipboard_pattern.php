<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// §45: existing (pre-§25) instances still store the nested clipboard pattern
//   %{%{{city} }%{{zone} }{postal_code}<BR>}
// which Utils_RecordBrowserCommon::replace_clipboard_pattern() cannot render — its regex
// excludes '%' from a block's content, so the OUTER %{...} never matches and stays literal
// (you see "%{{postal_code}" etc.). §25 fixed only the default for fresh installs + the dev DB;
// this patch fixes EXISTING databases on upgrade. Surgical: only rows containing the exact
// broken nested block are touched (custom patterns are left alone). Idempotent.

$old = '%{%{{city} }%{{zone} }{postal_code}<BR>}';
$new = '%{{city} {zone} {postal_code}<BR>}';

foreach (DB::GetAll('SELECT tab, pattern FROM recordbrowser_clipboard_pattern') as $r) {
    if (strpos((string) $r['pattern'], $old) === false) continue;
    DB::Execute('UPDATE recordbrowser_clipboard_pattern SET pattern=%s WHERE tab=%s',
        array(str_replace($old, $new, $r['pattern']), $r['tab']));
}
