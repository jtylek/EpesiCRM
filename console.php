#!/usr/bin/env php
<?php
// application.php

define('SET_SESSION', false);
require 'include.php';

use Epesi\Console\CacheRebuildCommand;
use Epesi\Console\Maintenance\MaintenanceStatusCommand;
use Epesi\Console\Modules\InstallModuleCommand;
use Epesi\Console\Modules\UninstallModuleCommand;
use Epesi\Console\ThemeRebuildCommand;
use Symfony\Component\Console\Application;
use Epesi\Console\Modules\ListModulesCommand;
use Epesi\Console\Modules\DisableModuleCommand;
use Epesi\Console\Modules\EnableModuleCommand;

ModuleManager::load_modules();

$application = new Application();
$application->add(new ListModulesCommand());
$application->add(new DisableModuleCommand());
$application->add(new EnableModuleCommand());
$application->add(new CacheRebuildCommand());
$application->add(new ThemeRebuildCommand());
$application->add(new MaintenanceStatusCommand());
$application->add(new InstallModuleCommand());
$application->add(new UninstallModuleCommand());
$application->run();