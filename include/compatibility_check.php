<?php
/**
 * Shared PHP/environment compatibility checks.
 *
 * Single source of truth for the "is this host fit to run EPESI" checks -
 * used by both check.php (setup.php's "Compatibility check" step, also
 * reachable stand-alone or from update.php) and admin/modules/ConfigInfo.php
 * (the logged-in "PHP Environment & config.php" admin screen). The two used
 * to keep separate, drifting copies of this logic (e.g. the memcached check
 * only existed in check.php) - this file is the fix.
 *
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CompatibilityCheck {

    // Read-only checks (PHP version, ini settings, extensions). Safe to run
    // in any context - fresh install (no DB/config yet) or the live admin
    // panel - unlike database_permission_check() below.
    public static function environment_checks() {
        return array(
            self::system_check(),
            self::error_reporting_check(),
            self::script_execution_check(),
            self::features_check(),
        );
    }

    private static function system_check() {
        $php_version = phpversion();
        // EPESI's own code (constructor property promotion etc., see
        // CLAUDE.md) parses only on PHP 8.0+; this checkout targets 8.2.
        $desired_version = '8.0';
        $php_version_ok = version_compare($php_version, $desired_version, '>=');
        $status = $php_version_ok ? $php_version : $php_version . ' - EPESI requires at least PHP ' . $desired_version . ' (8.2 recommended)';
        $tests = array(
            array('label' => 'PHP version', 'status' => $status, 'severity' => $php_version_ok ? 0 : 2),
        );
        return array('label' => 'System', 'tests' => $tests, 'solution' => 'http://forum.epesibim.com');
    }

    private static function error_reporting_check() {
        $err = error_reporting();
        $strict = (($err | E_STRICT) == $err);
        $display = ini_get('display_errors');

        $tests = array();
        $tests[] = array('label'=>'Strict errors reporting', 'status'=>!$strict?'Disabled':'Enabled', 'severity'=>!$strict?0:2);
        $tests[] = array('label'=>'Error display', 'status'=>$display?'On':'Off', 'severity'=>$display?0:1);

        return array('label'=>'Error reporting', 'tests'=>$tests, 'solution'=>'http://forum.epesibim.com');
    }

    private static function script_execution_check() {
        $mem = ini_get('memory_limit');
        if (!str_contains($mem, 'M')) $mem_s = 2;
        else {
            $mem = str_replace('M', '', $mem);
            if ($mem<32) $mem_s = 2;
            elseif ($mem==32) $mem_s = 1;
            else $mem_s = 0;
            $mem .= ' MB';
        }

        $upload_size = ini_get('upload_max_filesize');
        if (!str_contains($upload_size, 'M')) $upload_size_s = 2;
        else {
            $upload_size = str_replace('M', '', $upload_size);
            if ($upload_size<8) $upload_size_s = 2;
            elseif ($upload_size==8) $upload_size_s = 1;
            else $upload_size_s = 0;
            $upload_size .= ' MB';
        }

        $post_size = ini_get('post_max_size');
        if (!str_contains($post_size, 'M')) $post_size_s = 2;
        else {
            $post_size = str_replace('M', '', $post_size);
            if ($post_size<16) $post_size_s = 2;
            elseif ($post_size==16) $post_size_s = 1;
            else $post_size_s = 0;
            $post_size .= ' MB';
        }

        if (version_compare(PHP_VERSION, '5.4') >= 0)
            $safe_mode = false;
        else
            $safe_mode = ini_get('safe_mode');

        // setlocale() is process-wide, not request-scoped - save/restore
        // around the probe so running this from the (far more frequently
        // visited) admin panel can't leak a Polish/POSIX locale into other
        // requests served by the same long-lived worker process.
        $prev_lc_all = setlocale(LC_ALL, 0);
        $prev_lc_numeric = setlocale(LC_NUMERIC, 0);

        $lang_code = 'pl';
        setlocale(LC_ALL,$lang_code.'_'.strtoupper($lang_code).'.utf8',
                $lang_code.'_'.strtoupper($lang_code).'.UTF-8',
                $lang_code.'.utf8',
                $lang_code.'.UTF-8','polish');
        setlocale(LC_NUMERIC,'en_EN.utf8','en_EN.UTF-8','en_US.utf8','en_US.UTF-8','C','POSIX','en_EN','en_US','en','en.utf8','en.UTF-8','english');
        $str = print_r(1.1,true);

        if (!str_contains($str,'.')) {
            $loc = 'ERROR';
            $loc_s = 1;
        } else {
            $loc = 'OK';
            $loc_s = 0;
        }

        setlocale(LC_ALL, $prev_lc_all);
        setlocale(LC_NUMERIC, $prev_lc_numeric);

        if (extension_loaded('openssl')) {
            $ssl = 'OK';
            $ssl_s = 0;
        } else {
            $ssl = "ERROR";
            $ssl_s = 1;
        }

        $tests = array();
        $tests[] = array('label'=>'Safe mode', 'status'=>!$safe_mode?'Disabled':'Enabled', 'severity'=>!$safe_mode?0:2);
        $tests[] = array('label'=>'Memory limit', 'status'=>$mem, 'severity'=>$mem_s);
        $tests[] = array('label'=>'Upload file size', 'status'=>$upload_size, 'severity'=>$upload_size_s);
        $tests[] = array('label'=>'POST max size', 'status'=>$post_size, 'severity'=>$post_size_s);
        $tests[] = array('label'=>'Locale settings', 'status'=>$loc, 'severity'=>$loc_s);
        $tests[] = array('label'=>'SSL enabled', 'status'=>$ssl, 'severity'=>$ssl_s);

        return array('label'=>'Script execution', 'tests'=>$tests, 'solution'=>'http://forum.epesibim.com');
    }

    // PHP extensions EPESI's own code (not vendor/, not the bundled
    // Roundcube webmail tree) calls directly. Severity 2 = called
    // unconditionally on a common path (breaks the app/feature outright);
    // severity 1 = optional or edge-case (host still runs without it).
    private static function features_check() {
        $zip = class_exists('ZipArchive');
        $curl = extension_loaded('curl');
        $remote_fgc = ini_get('allow_url_fopen');
        $modules_writable = is_writable('modules');
        $data_writable = is_writable(DATA_DIR);
        $imagegd = function_exists('imagecreatefromjpeg');
        // include/config.php calls mb_internal_encoding() unconditionally on
        // every bootstrap - missing mbstring fatals the entire app.
        $mbstring = extension_loaded('mbstring');
        // Utils_FileStorage/Utils_Attachment call new finfo() unguarded for
        // upload mime-type detection.
        $fileinfo = class_exists('finfo');
        // Base_RegionalSettingsCommon::strftime() calls iconv() unguarded
        // for locale-aware date formatting on Windows hosts.
        $iconv = function_exists('iconv');
        // include/misc.php's EpesiHTML sanitizer calls xml_parser_create()
        // unguarded; ext-xml ships by default in virtually every PHP build.
        $xml = extension_loaded('xml');
        // Utils_FileStorage (Notes/attachments file previews) gates PDF
        // thumbnail generation on class_exists('Imagick') and just skips
        // the thumbnail when it's missing - optional, not a hard dependency.
        $imagick = class_exists('Imagick');

        // Optional: session storage uses it when reachable (see setup.php's
        // memcached_session_available() / EpesiSessionMemcachedStorage in
        // include/session.php) - falls back to file-based sessions otherwise, so
        // this is advisory (severity 1), not a hard requirement (severity 2).
        $memcached_ext = extension_loaded('memcached') || extension_loaded('memcache');
        $memcached_server = false;
        if ($memcached_ext) {
            $sock = @fsockopen('127.0.0.1', 11211, $errno, $errstr, 1);
            if ($sock) {
                $memcached_server = true;
                fclose($sock);
            }
        }
        if (!$memcached_ext)
            $memcached_status = 'Extension not loaded';
        elseif (!$memcached_server)
            $memcached_status = 'Extension loaded, server not reachable';
        else
            $memcached_status = 'Available';

        $tests = array();
        $tests[] = array('label'=>'Remote file_get_contents()', 'status'=>$remote_fgc?'Enabled':'Disabled', 'severity'=>$remote_fgc?0:2);
        $tests[] = array('label'=>'ZIPArchive library loaded', 'status'=>$zip?'Loaded':'Not found', 'severity'=>$zip?0:2);
        $tests[] = array('label'=>'cURL library loaded', 'status'=>$curl?'Loaded':'Not found', 'severity'=>$curl?0:1);
        $tests[] = array('label'=>'Modules directory writable', 'status'=>$modules_writable?'Yes':'No', 'severity'=>$modules_writable?0:1);
        $tests[] = array('label'=>'Data directory writable', 'status'=>$data_writable?'Yes':'No', 'severity'=>$data_writable?0:2);
        $tests[] = array('label'=>'PHP GD extension - image processing', 'status'=>$imagegd?'Yes':'No', 'severity'=>$imagegd?0:2);
        $tests[] = array('label'=>'Mbstring extension - required on every request', 'status'=>$mbstring?'Loaded':'Not found', 'severity'=>$mbstring?0:2);
        $tests[] = array('label'=>'Fileinfo extension - file upload mime detection', 'status'=>$fileinfo?'Loaded':'Not found', 'severity'=>$fileinfo?0:2);
        $tests[] = array('label'=>'XML extension - HTML sanitizer', 'status'=>$xml?'Loaded':'Not found', 'severity'=>$xml?0:1);
        $tests[] = array('label'=>'Iconv extension - Windows date/locale formatting', 'status'=>$iconv?'Loaded':'Not found', 'severity'=>$iconv?0:1);
        $tests[] = array('label'=>'Imagick (optional PDF thumbnails)', 'status'=>$imagick?'Loaded':'Not found', 'severity'=>$imagick?0:1);
        $tests[] = array('label'=>'Memcached (optional session storage)', 'status'=>$memcached_status, 'severity'=>$memcached_server?0:1);

        if (defined('DATABASE_DRIVER')) {
            $db_ext = self::database_driver_extension();
            if ($db_ext !== null) {
                $db_ext_loaded = extension_loaded($db_ext);
                $tests[] = array('label'=>'Database driver extension ('.$db_ext.')', 'status'=>$db_ext_loaded?'Loaded':'Not found', 'severity'=>$db_ext_loaded?0:2);
            }
        }

        return array('label'=>'Features', 'tests'=>$tests, 'solution'=>'http://forum.epesibim.com');
    }

    // Mirrors DB::is_mysql()/is_postgresql() (include/database.php) without
    // requiring the DB class to be loaded, since environment_checks() must
    // also work pre-install (config.php not written yet, DATABASE_DRIVER
    // undefined - callers already guard on defined('DATABASE_DRIVER')).
    private static function database_driver_extension() {
        if (stripos(DATABASE_DRIVER, 'postgre') !== false) return 'pgsql';
        if (stripos(DATABASE_DRIVER, 'mysql') !== false) return 'mysqli';
        return null;
    }

    // Mutating probe: creates a scratch table, exercises ALTER/INSERT/
    // UPDATE/LOCK/DELETE/DROP against it. Only meaningful once a live DB
    // connection exists - callers must guard on that themselves (check.php's
    // $config flag) rather than this method silently no-oping, since
    // "database not connected" and "database connected but broken" need to
    // read differently.
    public static function database_permission_check() {
        ob_start();
        $exists = @DB::GetOne('SELECT 1 FROM modules WHERE NOT EXISTS (SELECT 1 FROM test WHERE 1=2)');
        if (!$exists) {
            $create = @DB::CreateTable('test', 'id I4 AUTO KEY', array('constraints'=>''));
            $alter = @DB::Execute('ALTER TABLE test ADD COLUMN field_name INTEGER');
        } else $alter = $create = null;
        $insert = @DB::Execute('INSERT INTO test (id) VALUES (1)');
        $update = @DB::Execute('UPDATE test SET id=1 WHERE id=1');
        if (DB::is_mysql()) {
            $lock = DB::GetOne('SELECT GET_LOCK(%s,%d)',array('test',ini_get('max_execution_time')));
            $lock &= !DB::GetOne('SELECT IS_FREE_LOCK(%s)',array('test'));
            $end_lock = DB::GetOne('SELECT RELEASE_LOCK(%s)',array('test'));
        }
        $delete = @DB::Execute('DELETE FROM test');
        $drop = @DB::DropTable('test');
        ob_end_clean();

        $db_tests = array();
        if ($create===null)
            $db_tests[] = array('label'=>'CREATE permission', 'status'=>'Unknown', 'severity'=>1);
        else
            $db_tests[] = array('label'=>'CREATE permission', 'status'=>$create?'OK':'Failed', 'severity'=>$create?0:2);
        if ($alter===null)
            $db_tests[] = array('label'=>'ALTER permission', 'status'=>'Unknown', 'severity'=>1);
        else
            $db_tests[] = array('label'=>'ALTER permission', 'status'=>$alter?'OK':'Failed', 'severity'=>$alter?0:2);
        $db_tests[] = array('label'=>'INSERT permission', 'status'=>$insert?'OK':'Failed', 'severity'=>$insert?0:2);
        $db_tests[] = array('label'=>'UPDATE permission', 'status'=>$update?'OK':'Failed', 'severity'=>$update?0:2);
        if (DB::is_mysql())
            $db_tests[] = array('label'=>'LOCK permission', 'status'=>$lock?'OK':'Failed', 'severity'=>$lock?0:2);
        $db_tests[] = array('label'=>'DELETE permission', 'status'=>$delete?'OK':'Failed', 'severity'=>$delete?0:2);
        $db_tests[] = array('label'=>'DROP permission', 'status'=>$drop?'OK':'Failed', 'severity'=>$drop?0:2);

        return array('label'=>'Database permissions', 'tests'=>$db_tests, 'solution'=>'http://forum.epesibim.com');
    }

}

?>
