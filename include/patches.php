<?php

/**
 * @author    Adam Bukowski <abukowski@telaxus.com>
 * @version   0.2
 * @copyright Copyright &copy; 2012,2014 Telaxus LLC
 * @license   MIT
 * @package   epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class PatchException extends Exception
{
}

class NotEnoughExecutionTimeException extends Exception
{
}

class PatchUtil
{

    /**
     * Apply all new patches
     *
     * @param $die_on_error bool Die on error within patch
     *
     * @return Patch[] array of patches applied
     * @throws ErrorException when log file is unavailable
     */
    static function apply_new($die_on_error = false)
    {
        set_time_limit(0);
        self::start_timing();
        $logfile = DATA_DIR . '/patches_log.txt';
        $fh = fopen($logfile, 'a');
        if ($fh === false) {
            throw new ErrorException("Can't open patches log file " . $logfile);
        }

        fwrite($fh, "========= " . date("Y/m/d H:i:s") . " =========\n");
        $patches = self::list_patches();
        foreach ($patches as $p) {
            $p->apply();
            fwrite($fh, $p->get_apply_log());
            if ($p->get_apply_status() !== Patch::STATUS_SUCCESS) {
                if ($die_on_error) {
                    $msg = "PATCH APPLY ERROR: " . $p->get_apply_error_msg();
                    trigger_error($msg, E_USER_ERROR);
                }
                break;
            }
        }
        fclose($fh);
        return $patches;
    }

    /**
     * Mark all new patches for the module as applied, without applying them.
     *
     * @param string $module Module name
     *
     * @return Patch[]
     */
    static function mark_applied($module)
    {
        $patches = self::list_for_module($module);
        foreach ($patches as $patch) {
            $patch->mark_applied();
        }
        return $patches;
    }

    /**
     * List patches available in the system.
     *
     * @param bool $only_new True to list not applied, false to list all
     *
     * @return Patch[] Array of patches
     */
    static function list_patches($only_new = true)
    {
        $patches = self::list_core($only_new);
        $modules_list = array_keys(ModuleManager::$modules);
        foreach ($modules_list as $module) {
            $x = self::list_for_module($module, $only_new);
            $patches = array_merge($patches, $x);
        }
        self::_sort_patches_by_date($patches);
        return $patches;
    }

    /**
     * @param string $module   Module name
     * @param bool   $only_new True to list not applied, false to list all
     *
     * @return Patch[]
     */
    static function list_for_module($module, $only_new = true)
    {
        return self::_list_patches(self::_module_patches_path($module), $only_new);
    }

    /**
     * Return patches from the old installations. Located in /patches directory
     *
     * @param bool $only_new True to list not applied, false to list all
     *
     * @return Patch[]
     */
    static function list_core($only_new = true)
    {
        $patches = self::_list_patches('patches/', $only_new, true);
        return $patches;
    }

    /**
     * @param string $directory Directory with patches
     * @param bool   $only_new  True to list not applied, false to list all
     * @param bool   $legacy    True when patch is located in /tools directory.
     *                          Required to calculate proper patch id. Should not
     *                          be used, because it's for compatibility.
     *
     * @return Patch[]
     */
    private static function _list_patches($directory, $only_new = false, $legacy = false)
    {
        if (!is_dir($directory)) {
            return array();
        }

        $patches_db = new PatchesDB();

        $patches = array();
        $directory = rtrim($directory, '/\\') . '/';
        $d = dir($directory);
        while (false !== ($entry = $d->read())) {
            $entry = $directory . $entry;
            if (self::_is_patch_file($entry)) {
                $x = new Patch($entry, $patches_db);
                $x->set_legacy($legacy);
                if ($only_new) {
                    if (!$x->was_applied()) {
                        $patches[] = $x;
                    }
                } else {
                    $patches[] = $x;
                }
            }
        }
        $d->close();
        self::_sort_patches_by_date($patches);
        return $patches;
    }

    private static function _is_patch_file($file)
    {
        if (!is_file($file)) {
            return false;
        }
        if (basename($file) == 'index.php') {
            return false;
        }
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return strtolower($ext) == 'php';
    }

    private static function _module_patches_path($module)
    {
        return 'modules/' . ModuleManager::get_module_dir_path($module) . '/patches/';
    }

    private static function _sort_patches_by_date(array & $patches)
    {
        usort($patches, array('Patch', 'cmp_by_date'));
    }

// ******************* Database Patch functions *************
    static function db_add_column($table_name, $table_column, $table_column_def)
    {
        // First check if table needs to be altered
        if (!array_key_exists(strtoupper($table_column), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->AddColumnSQL($table_name, $table_column . ' ' . $table_column_def);
            foreach ($q as $qq) {
                DB::Execute($qq);
            }
            return true;
        } else {
            return false;
        }
    }

    static function db_drop_column($table_name, $table_column)
    {
        // First check if table needs to be altered
        if (array_key_exists(strtoupper($table_column), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->DropColumnSQL($table_name, $table_column);
            foreach ($q as $qq) {
                DB::Execute($qq);
            }
            return true;
        } else {
            return false;
        }
    }

    static function db_rename_column($table_name, $old_table_column, $new_table_column, $table_column_def)
    {
        // First check if column exists
        if (array_key_exists(strtoupper($old_table_column), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->RenameColumnSQL($table_name, $old_table_column, $new_table_column, $new_table_column . ' ' . $table_column_def);
            foreach ($q as $qq) {
                DB::Execute($qq);
            }
            return true;
        } else {
            return false;
        }
    }

    static function db_alter_column($table_name, $table_column_name, $table_column_def)
    {
        // First check if column exists
        if (array_key_exists(strtoupper($table_column_name), DB::MetaColumnNames($table_name))) {
            $q = DB::dict()->AlterColumnSQL($table_name, $table_column_name . ' ' . $table_column_def);
            foreach ($q as $qq) {
                DB::Execute($qq);
            }
            return true;
        } else {
            return false;
        }
    }

    /***** TIME MANAGEMENT FUNCTIONS *****/

    private static $start_time;
    private static $deadline_time;

    private static function start_timing($total_run_time_in_seconds = 30)
    {
        self::$start_time = microtime(true);
        self::$deadline_time = self::$start_time + $total_run_time_in_seconds;
    }

    /**
     * Require some amount of time. Throws exception to stop patch execution
     * if there is not enough time. Exception is catched by the apply function
     * of Patch object, and patch is marked as error.
     *
     * @param float $seconds Seconds of required time before the execution deadline.
     *
     * @throws NotEnoughExecutionTimeException
     */
    public static function require_time($seconds)
    {
        $now = microtime(true);
        if ($now + $seconds > self::$deadline_time) {
            throw new NotEnoughExecutionTimeException();
        }
    }

}

class PatchesDB
{

    function __construct()
    {
        $this->_check_table();
    }

    private function _check_table()
    {
        $tables_db = DB::MetaTables();
        if (!in_array('patches', $tables_db)) {
            DB::CreateTable('patches', "id C(32) KEY NOTNULL");
        } //md5 id
    }

    public function was_applied($identifier)
    {
        return 1 == DB::GetOne('SELECT 1 FROM patches WHERE id=%s', array($identifier));
    }

    public function mark_applied($identifier)
    {
        DB::Execute('INSERT INTO patches VALUES(%s)', array($identifier));
    }

}

class Patch
{
    /**
     * When patch has not been run, but it's not installed in the system.
     */
    const STATUS_NEW = 'NEW';
    /**
     * Patch has been applied with success.
     */
    const STATUS_SUCCESS = 'SUCCESS';
    /**
     * Timeout occured during patch execution. It will run again.
     */
    const STATUS_TIMEOUT = 'TIMEOUT';
    /**
     * Some error occured during patch execution.
     */
    const STATUS_ERROR = 'ERROR';
    /**
     * Patch has been applied in the system. It won't run.
     */
    const STATUS_OLD = 'OLD';

    private $creation_date;
    private $module;
    private $short_description;
    private $file;
    private $DB;
    private $legacy;
    private $apply_log;
    private $apply_status;
    private $apply_error;

    function __construct($file, PatchesDB $db, $is_legacy = false)
    {
        $this->file = $file;
        $this->_parse_module();
        $this->_parse_filename();
        $this->DB = $db;
        $this->legacy = $is_legacy;

        $this->apply_status = $this->was_applied()
            ? self::STATUS_OLD : self::STATUS_NEW;
    }

    static function cmp_by_date($patch1, $patch2)
    {
        $p1_date = $patch1->get_creation_date();
        $p2_date = $patch2->get_creation_date();
        if ((!$p1_date && !$p2_date) || $p1_date == $p2_date) {
            return strcmp($patch1->file, $patch2->file);
        }
        if (!$p1_date) {
            return -1;
        }
        if (!$p2_date) {
            return 1;
        }
        return strcmp($p1_date, $p2_date);
    }

    function get_creation_date()
    {
        return $this->creation_date;
    }

    function get_module()
    {
        return $this->module ? $this->module : 'EPESI Core';
    }

    function get_short_description()
    {
        return $this->short_description;
    }

    static function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (!(error_reporting() & $errno)) {
            return;
        }
        throw new PatchException("Error occured.\nFile: $errfile\nLine: $errline\nMessage: $errstr\n" . print_r(debug_backtrace(), true));
    }

    private function output_bufferring_interrupted($str)
    {
        return "Patch output buffering interrupted. Maybe die or exit function was used.\nFile: {$this->file}\nOutput buffer: $str";
    }

    function apply()
    {
        if (!file_exists($this->file)) {
            return false;
        }

        if ($this->apply_status == self::STATUS_OLD) {
            return false;
        }

        $this->init_checkpoints();
        set_error_handler(array('Patch', 'error_handler'));
        ob_start(array($this, 'output_bufferring_interrupted'));
        try {
            PatchUtil::require_time(1);
            include $this->file;
            $this->apply_status = self::STATUS_SUCCESS;
        } catch (NotEnoughExecutionTimeException $ex) {
            $this->apply_status = self::STATUS_TIMEOUT;
        } catch (PatchException $e) {
            $this->apply_status = self::STATUS_ERROR;
            $this->apply_error = $e->getMessage();
        } catch (Exception $e) {
            $this->apply_status = self::STATUS_ERROR;
            $this->apply_error = "Exception occured.\nFile: {$e->getFile()}\nLine: {$e->getLine()}\nMessage: {$e->getMessage()}";
        }
        $output = ob_get_clean();
        restore_error_handler();
        $this->apply_log = "[md5: {$this->get_identifier()}] [{$this->apply_status}] {$this->get_file()}\n";
        if ($output) {
            $this->apply_log .= " === OUTPUT ===\n$output\n === END OUTPUT ===\n";
        }
        if ($this->apply_error) {
            $this->apply_log .= " !!! ERROR !!!\n{$this->apply_error}\n !!! END ERROR !!!\n";
        }
        if ($this->apply_status == self::STATUS_SUCCESS) {
            $this->mark_applied();
            $this->destroy_checkpoints();
            return true;
        }
        return false;
    }

    function mark_applied()
    {
        $this->DB->mark_applied($this->get_identifier());
    }

    function was_applied()
    {
        return $this->DB->was_applied($this->get_identifier());
    }

    function get_identifier()
    {
        $str = $this->legacy ? 'tools/' . $this->file : $this->file;
        return md5($str);
    }

    function get_legacy()
    {
        return $this->legacy;
    }

    function set_legacy($legacy)
    {
        $this->legacy = $legacy;
    }

    function get_file()
    {
        return $this->file;
    }

    function get_apply_log()
    {
        return $this->apply_log;
    }

    function get_apply_status()
    {
        return $this->apply_status;
    }

    function get_apply_error_msg()
    {
        return $this->apply_error;
    }

    private function _parse_module()
    {
        $dirname = pathinfo($this->file, PATHINFO_DIRNAME);
        $modules_dir = 'modules/';
        if (strpos($dirname, $modules_dir) === 0) {
            $this->module = substr($dirname, strlen($modules_dir), -strlen('/patches'));
        }
    }

    private function _parse_filename()
    {
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

    private function set_creation_date($creation_date)
    {
        if (!is_numeric($creation_date)) {
            throw new Exception("Wrong patch creation date - use this filename scheme: YYYYMMDD_short_description.php");
        }
        $this->creation_date = $creation_date;
    }

    private function set_short_description($short_description)
    {
        if (strlen($short_description) == 0) {
            throw new Exception("Wrong patch description - use this filename scheme: YYYYMMDD_short_description.php");
        }
        $this->short_description = str_replace('_', ' ', $short_description);
    }

    /***** checkpoints *****/

    /**
     * @var array Checkpoints data
     */
    private static $checkpoints_data = array();

    /**
     * @var string Checkpoints file path
     */
    private static $checkpoints_file;

    /**
     * Get unique file path per patch to use as checkpoints data file
     * @return string
     */
    private function get_checkpoints_file()
    {
        $file = rtrim(DATA_DIR, '/\\') . '/patch_checkpoints_' . $this->get_identifier() . '.dat';
        return $file;
    }

    /**
     * Init checkpoints data file.
     *
     * We'd like to store them separately, because in some scenarios patches
     * may be run simultanously. E.g.
     * 1. Run patch B without success - checkpoint file is left
     * 2. Create patch A (before B)
     * 3. Running patch A could overwrite B's checkpoints.
     * That's the reason why patch id is in the filename
     */
    private function init_checkpoints()
    {
        $file = $this->get_checkpoints_file();
        self::$checkpoints_file = $file;
        self::$checkpoints_data = array();
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = @unserialize($content);
            if (is_array($data)) {
                self::$checkpoints_data = $data;
            }
        }
    }

    /**
     * Remove checkpoints data file.
     */
    private function destroy_checkpoints()
    {
        @unlink($this->get_checkpoints_file());
    }

    /**
     * Save a checkpoint for currently executed patch.
     * Use only in the patch context.
     *
     * Checkpoints will be stored until patch is fully applied.
     *
     * @param string $name Unique checkpoint name - unique per patch
     * @param mixed  $data Any data to store in the checkpoint. By default
     *                     it's just true to store that you've done something
     */
    public static function save_checkpoint($name, $data = true)
    {
        self::$checkpoints_data[$name] = $data;
        $serialized_checkpoints_data = serialize(self::$checkpoints_data);
        file_put_contents(self::$checkpoints_file, $serialized_checkpoints_data);
    }

    /**
     * Read checkpoint's data.
     *
     * @param string $name    Unique checkpoint name
     * @param mixed  $default Default value
     *
     * @return mixed Data saved in the checkpoint. $default if checkpoint has not
     *               been set.
     */
    public static function read_checkpoint($name, $default = null)
    {
        $ret = $default;
        if (isset(self::$checkpoints_data[$name])) {
            $ret = self::$checkpoints_data[$name];
        }
        return $ret;
    }

    /**
     * Calls PatchUtil::require_time
     *
     * Require some amount of time. Throws exception to stop patch execution
     * if there is not enough time. Exception is catched by the apply function
     * of Patch object, and patch is marked as error.
     *
     * @param float $seconds Seconds of required time before the execution deadline.
     *
     * @throws NotEnoughExecutionTimeException
     * @see PatchUtil::require_time
     */
    public static function require_time($seconds)
    {
        PatchUtil::require_time($seconds);
    }

}
