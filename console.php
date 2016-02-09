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

class ModuleLoader {
    const all_modules = ':all:';

    private $lpa;
    private $lpa_count;
    private $lpa_index = 0;
    private $loaded_modules = array();
    private $initialized = false;

    private function init() {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->lpa = ModuleManager::get_load_priority_array();
            $this->lpa_count = count($this->lpa);
            ModulesAutoloader::enable();
        }
    }

    function load($modules) {
        $this->init();

        if (!is_array($modules))
            $modules = array($modules);

        foreach ($modules as $m) {
            if (array_key_exists($m, ModuleManager::$modules))
                continue;

            while ($this->lpa_index < $this->lpa_count) {
                $row = $this->lpa[$this->lpa_index++];
                $module = $row['name'];
                $version = $row['version'];
                ModuleManager :: include_common($module, $version);
                ModuleManager :: register($module, $version, ModuleManager::$modules);
                if ($m != self::all_modules && $module == $m)
                    break;
            }
        }
    }

}

(new ModuleLoader())->load(array('Base_User', 'Base_User_Login', 'Base_Acl', 'Base_User_Settings'));


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