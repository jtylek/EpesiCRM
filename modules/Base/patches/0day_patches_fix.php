<?php

$patches_db = new PatchesDB();

$patches = PatchUtil::list_patches(false);
foreach ($patches as $patch) {
    $needle = 'patches/';
    $filename = $patch->get_file();
    $backslash_pos = strpos($filename, $needle);
    if ($backslash_pos !== false) {
        $filename[$backslash_pos + strlen($needle) - 1] = '\\';
        $id = md5($filename);
        if ($patches_db->was_applied($id)) {
            $patch->mark_applied();
        }
    }
}

?>