#!/usr/bin/env php
<?php
// application.php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;

require_once 'vendor/autoload.php';

$input = new ArgvInput();
$data_dir = $input->getParameterOption('--data-dir', false);

define('SET_SESSION', false);
if ($data_dir) {
    define('DATA_DIR', $data_dir);
}
require 'include.php';
ModuleManager::load_modules();

$application = new Application();
$application->getDefinition()->addOption(new InputOption('data-dir', null, InputOption::VALUE_REQUIRED, 'Data directory to use'));

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
$application->add(new \Epesi\Console\Develop\CreateModuleCommand());
$application->add(new \Epesi\Console\Develop\CreatePatchCommand());
$application->add(new \Epesi\Console\Develop\CreateTestModuleCommand());
$application->add(new \Epesi\Console\ShellCommand());
$application->add(new \Epesi\Console\RebuildAllCommand());
$application->add(new \Epesi\Console\RemoveAllCommand());
$application->run($input);
