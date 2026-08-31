#!/usr/bin/env php
<?php
// application.php

use Symfony\Component\Console\Application;

require_once 'vendor/autoload.php';

define('SET_SESSION', false);
require 'include.php';
ModuleManager::load_modules();

$application = new Application();

$application->add(new \Epesi\Console\Modules\ListModulesCommand());
$application->add(new \Epesi\Console\Modules\DisableModuleCommand());
$application->add(new \Epesi\Console\Modules\EnableModuleCommand());
$application->add(new \Epesi\Console\Modules\InstallModuleCommand());
$application->add(new \Epesi\Console\Modules\UninstallModuleCommand());
$application->add(new \Epesi\Console\Modules\EnableAllModuleCommand());
$application->add(new \Epesi\Console\CacheRebuildCommand());
$application->add(new \Epesi\Console\ThemeRebuildCommand());
$application->add(new \Epesi\Console\Maintenance\MaintenanceStatusCommand());
$application->add(new \Epesi\Console\Maintenance\MaintenanceOnCommand());
$application->add(new \Epesi\Console\Maintenance\MaintenanceOffCommand());
$application->add(new \Epesi\Console\SearchClearCommand());
$application->add(new \Epesi\Console\SearchIndexCommand());
$application->add(new \Epesi\Console\Backup\BackupDbCommand());
$application->add(new \Epesi\Console\Backup\BackupFilesCommand());
$application->add(new \Epesi\Console\Backup\BackupAllCommand());
$application->add(new \Epesi\Console\Backup\ListBackupsCommand());
$application->add(new \Epesi\Console\Demo\GenerateContactsCommand());
$application->add(new \Epesi\Console\Demo\GeneratePhonecallsCommand());
$application->add(new \Epesi\Console\Demo\GenerateMeetingsCommand());
$application->add(new \Epesi\Console\Demo\GenerateTasksCommand());
$application->add(new \Epesi\Console\Demo\GenerateShoutboxCommand());
$application->add(new \Epesi\Console\Develop\CreateModuleCommand());
$application->add(new \Epesi\Console\Develop\CreatePatchCommand());
$application->add(new \Epesi\Console\Develop\CreateTestModuleCommand());
$application->add(new \Epesi\Console\Develop\CreateDistCommand());
$application->add(new \Epesi\Console\Develop\QueryBudgetCommand());
$application->add(new \Epesi\Console\ShellCommand());
$application->add(new \Epesi\Console\RebuildAllCommand());
$application->add(new \Epesi\Console\RemoveAllCommand());
$application->run($input);
