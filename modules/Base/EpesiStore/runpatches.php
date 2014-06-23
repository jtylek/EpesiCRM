<?php

define('CID', false);

require_once('../../../include.php');
ModuleManager::load_modules();

if (!Acl::i_am_admin()) {
    die("Access forbidden");
}

function ret($val)
{
    print($val);
    exit();
}

try {
    $patches = PatchUtil::apply_new();
} catch (ErrorException $e) {
    ret($e->getMessage());
}

ModuleManager::create_common_cache();
Base_ThemeCommon::themeup();
Base_LangCommon::update_translations();

foreach ($patches as $patch) {
    if ($patch->get_apply_status() !== Patch::STATUS_SUCCESS) {
        ret(0);
    }
}
ret(1);
