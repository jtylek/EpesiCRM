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
    static function log($message)
    {
        $logfile = DATA_DIR . '/patches_log.txt';
        file_put_contents($logfile, $message, FILE_APPEND);
    }

    /**
     * Apply all new patches
     *
     * @param $die_on_error bool Die on error within patch
     *
     * @return Patch[] array of patches that should be applied
     * @see PatchUtil::all_successful
     */
    static function apply_new($die_on_error = false)
    {
        set_time_limit(0);
        self::start_timing();

        self::log("========= " . date("Y/m/d H:i:s") . " =========\n");
        $patches = self::list_patches();
        foreach ($patches as $p) {
            self::log("[{$p->get_file()}] Running...\n");
            $p->apply();
            self::log($p->get_apply_log());
            $status = $p->get_apply_status();
            if ($status == Patch::STATUS_ERROR && $die_on_error) {
                $msg = "PATCH APPLY ERROR: " . $p->get_apply_error_msg();
                trigger_error($msg, E_USER_ERROR);
            }
            // break always if somethings is wrong
            if ($status != Patch::STATUS_SUCCESS) {
                break;
            }
        }
        return $patches;
    }

    /**
     * Check if all patches supplied as argument has been successfully applied
     *
     * @param Patch[] $patches
     *
     * @return bool true when all applied, false otherwise
     */
    public static function all_successful($patches)
    {
        /** @var Patch $p */
        foreach ($patches as $p) {
            if ($p->get_apply_status() != Patch::STATUS_SUCCESS) {
                return false;
            }
        }
        return true;
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
    private static $disable_time_management = false;

    private static function start_timing($total_run_time_in_seconds = 30)
    {
        if (self::$disable_time_management) {
            return;
        }
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
        if (self::$disable_time_management) {
            return;
        }
        $now = microtime(true);
        // if running time is less than a second,
        // then allow every query, even if it's too long
        $allow = ($now - self::$start_time < 1);
        if ($now + $seconds > self::$deadline_time) {
            if ($allow) {
                // issue a warning
                self::log("Patch requires too much execution time ($seconds), but running anyway\n");
            } else {
                throw new NotEnoughExecutionTimeException();
            }
        }
    }

    public static function disable_time_management($val = true)
    {
        self::$disable_time_management = $val;
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

    /**
     * @var Patch|null Currently executed patch with the apply.
     *                 May be null if no patch is executed
     */
    public static $current_patch;

    private $creation_date;
    private $module;
    private $short_description;
    private $file;
    private $DB;
    private $legacy;
    private $apply_log;
    private $apply_status;
    private $apply_error;
    private $user_message;

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

        self::$current_patch = $this;

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
        $this->apply_log = "[{$this->get_file()}] [{$this->get_identifier()}] {$this->apply_status}\n";
        if ($output) {
            $this->apply_log .= " === OUTPUT ===\n$output\n === END OUTPUT ===\n";
        }
        if ($this->apply_error) {
            $this->apply_log .= " !!! ERROR !!!\n{$this->apply_error}\n !!! END ERROR !!!\n";
        }
        $return = false;
        if ($this->apply_status == self::STATUS_SUCCESS) {
            $this->mark_applied();
            $this->destroy_checkpoints();
            $return = true;
        }
        self::$current_patch = null;
        return $return;
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
     * @var string Checkpoints file path
     */
    private static $checkpoints_dir;


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
        $dir = rtrim(DATA_DIR, '/\\') . '/patch_' . $this->get_identifier();
        self::$checkpoints_dir = $dir;
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new ErrorException('Cannot create patch checkpoints dir');
            }
        } else {
            mkdir($dir);
        }
    }

    /**
     * Remove checkpoints data dir.
     */
    private function destroy_checkpoints()
    {
        recursive_rmdir(self::$checkpoints_dir);
    }

    /**
     * Get checkpoint's object
     *
     * @param string $name Unique checkpoint name
     *
     * @return PatchCheckpoint Checkpoint object
     */
    public static function checkpoint($name)
    {
        $suffix = "/" . md5($name) . ".dat";
        $file = self::$checkpoints_dir . $suffix;
        $ret = PatchCheckpoint::get_for_file($file);
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

    /***** user messages *****/

    /**
     * Set user message like progress or something else that will be shown
     * to user during update process. This applies to currently running patch.
     * Call this method only within patch scope.
     *
     * @param string $message
     */
    public static function set_message($message)
    {
        if (self::$current_patch) {
            self::$current_patch->set_user_message($message);
        }
    }

    /**
     * Set user message like progress or something else that will be shown
     * to user during update process.
     *
     * @param string $message
     */
    public function set_user_message($message)
    {
        $this->user_message = $message;
    }

    /**
     * Get user message
     *
     * @return string|null message or null if it's not been set
     */
    public function get_user_message()
    {
        return $this->user_message;
    }
}

/**
 * Class PatchCheckpoint to store checkpoints on disk.
 * Do not use directly. Retrieve checkpoint from the patch context only.
 * Use Patch::checkpoint('name').
 */
class PatchCheckpoint
{
    /**
     * Read from serialized file or create new object
     *
     * @param $file
     *
     * @return mixed|PatchCheckpoint
     */
    public static function get_for_file($file)
    {
        $ret = null;
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $obj = @unserialize($content);
            if (is_object($obj)) {
                $obj->file = $file;
                $ret = $obj;
            }
        }
        if ($ret === null) {
            $ret = new PatchCheckpoint($file);
        }
        return $ret;
    }

    /**
     * New instance of checkpoint object.
     *
     * @param string $file Filename to store serialized object
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    private function save_data()
    {
        file_put_contents($this->file, serialize($this));
    }

    /**
     * Check is checkpoint done
     *
     * @return bool
     */
    public function is_done()
    {
        return $this->done;
    }

    /**
     * Set checkpoint as done
     */
    public function done()
    {
        $this->done = true;
        $this->save_data();
    }

    /**
     * Set value for the checkpoint.
     * You can also use array like access or properties
     *
     * All of those are the same:
     * $cp->test = 3; $cp['test'] = 3, $cp->set_value('test', 3)
     *
     * @param string $name
     * @param mixed  $val
     */
    public function set($name, $val)
    {
        $this->data[$name] = $val;
        $this->save_data();
    }

    /**
     * Get value for the checkpoint.
     * You can also use array like access or properties
     *
     * All of those are the same:
     * $cp->test; $cp['test'], $cp->get_value('test')
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        $ret = $default;
        if ($this->has($name)) {
            $ret = $this->data[$name];
        }
        return $ret;
    }

    public function has($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Require specific amount of time, but adjust it dynamically to the longest
     * run between calls to the function.
     *
     * First call - use argument value to require_time. Next - use calculated.
     *
     * @param float $time Default required time - only for the first run.
     *                    Every consecutive call to this function will use
     *                    stored time - calculated during runtime
     */
    public function require_time($time = 0.0)
    {
        $now = microtime(true);
        $last_measured = null;
        if (isset($this->timer_last_run)) {
            $last_measured = $now - $this->timer_last_run;
            if ($last_measured > $this->timer_max_time) {
                $this->timer_max_time = $last_measured;
            }
        }
        $this->timer_last_run = $now;
        if (isset($this->timer_max_time)) {
            $time = $this->timer_max_time;
        }
        $this->save_data();
        PatchUtil::require_time($time);
    }

    public function __sleep()
    {
        return array('timer_max_time', 'done', 'data');
    }

    private $timer_max_time;
    private $timer_last_run;

    private $done = false;
    private $data = array();
    private $file;
}
