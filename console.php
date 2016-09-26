#!/usr/bin/env php
<?php
// application.php

define('SET_SESSION', false);
require 'include.php';

use Symfony\Component\Console\Application;

ModuleManager::load_modules();

$application = new Application();
$application->add(new \Epesi\Console\Modules\ListModulesCommand());
$application->add(new \Epesi\Console\Modules\DisableModuleCommand());
$application->add(new \Epesi\Console\Modules\EnableModuleCommand());
$application->add(new \Epesi\Console\Modules\InstallModuleCommand());
$application->add(new \Epesi\Console\Modules\UninstallModuleCommand());
$application->add(new \Epesi\Console\CacheRebuildCommand());
$application->add(new \Epesi\Console\ThemeRebuildCommand());
$application->add(new \Epesi\Console\Maintenance\MaintenanceStatusCommand());
$application->add(new \Epesi\Console\Maintenance\MaintenanceOnCommand());
$application->add(new \Epesi\Console\Maintenance\MaintenanceOffCommand());
$application->add(new \Epesi\Console\SearchClearCommand());
$application->add(new \Epesi\Console\SearchIndexCommand());
$application->run();