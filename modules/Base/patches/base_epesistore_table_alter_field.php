<?php

//ModuleManager::uninstall($module_to_uninstall);
if (ModuleManager::is_installed('Base_EpesiStore') === -1)
    return;

PatchDBAlterColumn('epesi_store_modules', 'version', 'C(10)');

?>
