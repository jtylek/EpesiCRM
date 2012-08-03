<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 0.1
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class PatchUtil {

    /**
     * Apply all new patches
     * @return Patch[] array of patches applied
     * @throws ErrorException when log file is unavailable
     */
    static function apply_new() {
        set_time_limit(0);
        $logfile = DATA_DIR . '/patches_log.txt';
        $fh = fopen($logfile, 'a');
        if ($fh === false)
            throw ErrorException("Can't open patches log file " . $logfile);

        fwrite($fh, "========= " . date("Y/m/d H:i:s") . " =========\n");
        $patches = self::list_patches();
        foreach ($patches as $p) {
            $p->apply();
            fwrite($fh, $p->get_apply_log());
        }
        return $patches;
    }

    static function mark_applied($module) {
        $patches = self::list_for_module($module);
        foreach ($patches as $patch) {
            $patch->mark_applied();
        }
        return $patches;
    }

    static function list_patches($only_new = true) {
        $patches = self::list_core($only_new);
        $modules_list = array_keys(ModuleManager::$modules);
        foreach ($modules_list as $module) {
            $x = self::list_for_module($module, $only_new);
            $patches = array_merge($patches, $x);
        }
        self::_sort_patches_by_date($patches);
        return $patches;
    }

    static function list_for_module($module, $only_new = true) {
        return self::_list_patches(self::_module_patches_path($module), $only_new);
    }

    static function list_core($only_new = true) {
        $patches = self::_list_patches('patches/', $only_new, true);
        return $patches;
    }

    private static function _list_patches($directory, $only_new = false, $legacy = false) {
        if (!is_dir($directory))
            return array();

        $patches_db = new PatchesDB();

        $patches = array();
        $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
        $d = dir($directory);
        while (false !== ($entry = $d->read())) {
            $entry = $directory . $entry;
            if (self::_is_patch_file($entry)) {
                $x = new Patch($entry, $patches_db);
                $x->set_legacy($legacy);
                if ($only_new) {
                    if (!$x->was_applied())
                        $patches[] = $x;
                } else {
                    $patches[] = $x;
                }
            }
        }
        $d->close();
        self::_sort_patches_by_date($patches);
        return $patches;
    }

    private static function _is_patch_file($file) {
        if (!is_file($file))
            return false;
        if (basename($file) == 'index.php')
            return false;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return strtolower($ext) == 'php';
    }

    private static function _module_patches_path($module) {
        return 'modules/' . ModuleManager::get_module_dir_path($module) . '/patches/';
    }

    private static function _sort_patches_by_date(array & $patches) {
        usort($patches, array('Patch', 'cmp_by_date'));
    }

// ******************* Database Patch functions *************
    static function db_add_column($table_name, $table_column, $table_column_def) {
        // First check if table needs to be altered
        if (!array_key_exists(strtoupper($table_column), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->AddColumnSQL($table_name, $table_column . ' ' . $table_column_def);
            foreach ($q as $qq)
                DB::Execute($qq);
            return true;
        } else {
            return false;
        }
    }

    static function db_drop_column($table_name, $table_column) {
        // First check if table needs to be altered
        if (array_key_exists(strtoupper($table_column), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->DropColumnSQL($table_name, $table_column);
            foreach ($q as $qq)
                DB::Execute($qq);
            return true;
        } else {
            return false;
        }
    }

    static function db_rename_column($table_name, $old_table_column, $new_table_column, $table_column_def) {
        // First check if column exists
        if (array_key_exists(strtoupper($old_table_column), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->RenameColumnSQL($table_name, $old_table_column, $new_table_column, $new_table_column . ' ' . $table_column_def);
            foreach ($q as $qq)
                DB::Execute($qq);
            return true;
        } else {
            return false;
        }
    }

    static function db_alter_column($table_name, $table_column_name, $table_column_def) {
        // First check if column exists
        if (array_key_exists(strtoupper($table_column_name), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->AlterColumnSQL($table_name, $table_column_name . ' ' . $table_column_def);
            foreach ($q as $qq)
                DB::Execute($qq);
            return true;
        } else {
            return false;
        }
    }

}

class PatchesDB {

    function __construct() {
        $this->_check_table();
    }

    private function _check_table() {
        $tables_db = DB::MetaTables();
        if (!in_array('patches', $tables_db))
            DB::CreateTable('patches', "id C(32) KEY NOTNULL"); //md5 id
    }

    public function was_applied($identifier) {
        return 1 == DB::GetOne('SELECT 1 FROM patches WHERE id=%s', array($identifier));
    }

    public function mark_applied($identifier) {
        DB::Execute('INSERT INTO patches VALUES(%s)', array($identifier));
    }

}

class Patch {

    private $creation_date;
    private $module;
    private $short_description;
    private $file;
    private $DB;
    private $legacy;
    private $apply_log;
    private $apply_success;
    private $apply_error;

    function __construct($file, PatchesDB $db, $is_legacy = false) {
        $this->file = $file;
        $this->_parse_module();
        $this->_parse_filename();
        $this->DB = $db;
        $this->legacy = $is_legacy;
    }

    static function cmp_by_date($patch1, $patch2) {
        $p1_date = $patch1->get_creation_date();
        $p2_date = $patch2->get_creation_date();
        if ((!$p1_date && !$p2_date) || $p1_date == $p2_date)
            return strcmp($patch1->file, $patch2->file);
        if (!$p1_date)
            return -1;
        if (!$p2_date)
            return 1;
        return strcmp($p1_date, $p2_date);
    }

    function get_creation_date() {
        return $this->creation_date;
    }

    function get_module() {
        return $this->module ? $this->module : 'EPESI Core';
    }

    function get_short_description() {
        return $this->short_description;
    }

    private static $current_patch = null;

    static function error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
        if (!(error_reporting() & $errno))
            return;
        if (!self::$current_patch)
            return;
        self::$current_patch->apply_error .= "Error occured.\nFile: $errfile\nLine: $errline\nMessage: $errstr\n";
    }

    private function output_bufferring_interrupted($str) {
        return "Patch output buffering interrupted. Maybe die or exit function was used.\nFile: {$this->file}\nOutput buffer: $str";
    }

    function apply() {
        if (!file_exists($this->file))
            return false;

        self::$current_patch = $this;
        set_error_handler(array('Patch', 'error_handler'));
        ob_start(array($this, 'output_bufferring_interrupted'));
        try {
            include $this->file;
        } catch (Exception $e) {
            $this->apply_error = "Exception occured.\nFile: {$e->getFile()}\nLine: {$e->getLine()}\nMessage: {$e->getMessage()}";
        }
        $output = ob_get_clean();
        restore_error_handler();
        $success = $this->apply_error ? 'ERROR' : 'OK';
        $this->apply_log = "[md5: {$this->get_identifier()}] [$success] {$this->get_file()}\n";
        if ($output)
            $this->apply_log .= " === OUTPUT ===\n$output\n === END OUTPUT ===\n";
        if ($this->apply_error) {
            $this->apply_log .= " !!! ERROR !!!\n{$this->apply_error}\n !!! END ERROR !!!\n";
            $this->apply_success = false;
            return false;
        }
        $this->mark_applied();
        $this->apply_success = true;
        return true;
    }

    function mark_applied() {
        $this->DB->mark_applied($this->get_identifier());
    }

    function was_applied() {
        return $this->DB->was_applied($this->get_identifier());
    }

    function get_identifier() {
        $str = $this->legacy ? 'tools/' . $this->file : $this->file;
        return md5($str);
    }

    function get_legacy() {
        return $this->legacy;
    }

    function set_legacy($legacy) {
        $this->legacy = $legacy;
    }

    function get_file() {
        return $this->file;
    }

    function get_apply_log() {
        return $this->apply_log;
    }

    function get_apply_success() {
        return $this->apply_success;
    }

    function get_apply_error_msg() {
        return $this->apply_error;
    }

    private function _parse_module() {
        $dirname = pathinfo($this->file, PATHINFO_DIRNAME);
        $modules_dir = 'modules/';
        if (strpos($dirname, $modules_dir) === 0)
            $this->module = substr($dirname, strlen($modules_dir), -strlen('/patches'));
    }

    private function _parse_filename() {
        // to preserve compatibility PHP < 5.2
        $filename = basename($this->file, '.' . pathinfo($this->file, PATHINFO_EXTENSION));
        $sep_pos = strpos($filename, '_');
        if ($sep_pos === false) {
            $this->set_short_description($filename);
        }
        try {
            $this->set_creation_date(substr($filename, 0, $sep_pos));
            $this->set_short_description(substr($filename, $sep_pos + 1));
        } catch (Exception $e) {
            $this->set_short_description($filename);
        }
    }

    private function set_creation_date($creation_date) {
        if (!is_numeric($creation_date))
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