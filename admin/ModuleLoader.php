<?php

/**
 * Description of ModuleLoader
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
class ModuleLoader {
    const all_modules = ':all:';

    private $lpa;
    private $lpa_count;
    private $lpa_index = 0;
    private $initialized = false;

    private function init() {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->lpa = ModuleManager::get_load_priority_array();
            $this->lpa_count = count($this->lpa);
        }
    }

    function load($modules) {
        if ($modules == self::all_modules) {
            ModuleManager::load_modules();
            $this->initialized = true;
            return;
        }
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
                if ($module == $m)
                    break;
            }
        }
    }

}

?>
