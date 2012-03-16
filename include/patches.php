<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 0.1
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Patches {

    static function apply_new() {
        
    }

    static function list_all() {
        $patches = self::list_all_core();
        $modules_list = array_keys(ModuleManager::$modules);
        foreach ($modules_list as $module) {
            $x = self::list_all_for_module($module);
            $patches = array_merge($patches, $x);
        }
        return $patches;
    }

    static function list_new() {
        $patches = self::list_all();
        return self::remove_applied($patches);
    }

    static function list_all_for_module($module) {
        return self::list_patches(self::module_patches_path($module));
    }

    static function list_new_for_module($module) {
        $patches = self::list_all_for_module($module);
        return self::remove_applied($patches);
    }

    static function list_all_core() {
        return self::list_patches('patches/');
    }

    static function list_new_core() {
        $patches = self::list_all_core();
        return self::remove_applied($patches);
    }

    static function list_patches($directory) {
        if (!is_dir($directory))
            return array();

        $patches = array();
        $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
        $d = dir($directory);
        while (false !== ($entry = $d->read())) {
            $entry = $directory . $entry;
            if (self::is_patch_file($entry))
                $patches[] = new Patch($entry);
        }
        $d->close();
        return $patches;
    }

    private static function is_patch_file($file) {
        if (!is_file($file))
            return false;
        if (basename($file) == 'index.php')
            return false;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return strtolower($ext) == 'php';
    }

    private static function remove_applied(array $patches) {
        foreach ($patches as $k => $p) {
            if (self::was_applied($p))
                unset($patches[$k]);
        }
        return $patches;
    }

    private static function module_patches_path($module) {
        return 'modules/' . ModuleManager::get_module_dir_path($module) . '/patches/';
    }

    static function was_applied(Patch $patch) {
        return 1 == DB::GetOne('SELECT 1 FROM patches WHERE id=%s', array($patch->get_identifier()));
    }

}

class Patch {

    private $creation_date;
    private $short_description;
    private $file;

    function __construct($file) {
        $this->file = $file;
        $this->_parse_filename();
    }

    function get_creation_date() {
        return $this->creation_date;
    }

    function get_short_description() {
        return $this->short_description;
    }

    function apply() {
        if (file_exists($this->file))
            include $this->file;
    }

    function get_identifier() {
        return md5('tools/'.$this->file);
    }

    private function _parse_filename() {
        $tokens = basename($this->file, '.php');
        $sep_pos = strpos($tokens, '_');
        if ($sep_pos === false) {
            $this->set_short_description($tokens);
        }
        try {
            $this->set_creation_date(substr($tokens, 0, $sep_pos));
            $this->set_short_description(substr($tokens, $sep_pos + 1));
        } catch (Exception $e) {
            $this->set_short_description($tokens);
        }
    }

    private function set_creation_date($creation_date) {
        if (DateTime::createFromFormat("Ymd", $creation_date) === false)
            throw new Exception("Wrong patch creation date - use this filename scheme: YYYYMMDD_short_description.php");
        $this->creation_date = $creation_date;
    }

    private function set_short_description($short_description) {
        if (strlen($short_description) == 0)
            throw new Exception("Wrong patch description - use this filename scheme: YYYYMMDD_short_description.php");
        $this->short_description = str_replace('_', ' ', $short_description);
    }

}

?>