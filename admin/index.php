<?php

require_once('AdminIndex.php');
require_once('SimpleLayout.php');
require_once('ModuleLoader.php');

$module_loader = new ModuleLoader();
$layout = new SimpleLayout();
$admin = new AdminIndex($layout, $module_loader);
$admin->run();
?>