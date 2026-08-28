<?php
/**
 * Epesi Core updater.
 *
 * @author    Adam Bukowski <abukowski@telaxus.com> and Pawel Bukowski <pbukowski@telaxus.com>
 * @version   2.0
 * @copyright Copyright &copy; 2014, Telaxus LLC
 * @license   MIT
 * @package   epesi-base
 */
defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);


class EpesiPackageDownloader
{
    private $versions;

    private function __construct()
    {
        self::check_system_requirements();
    }

    public static function instance()
    {
        static $instance;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    public function get_update_json()
    {
        $ret = self::download('http://ess.epe.si/update.json');
        $update_json = @json_decode($ret, true);
        if ($update_json === null) {
            throw new ErrorException('Cannot decode update.json file: ' . $ret);
        }
        return $update_json;
    }

    public function get_versions()
    {
        if (!$this->versions) {
            $update_json = $this->get_update_json();
            if (!isset($update_json['files'])) {
                throw new ErrorException('No files defined in update.json');
            }
            $this->versions = $update_json['files'];
        }
        return $this->versions;
    }

    public function get_latest_package_info()
    {
        $versions = $this->get_versions();
        $latest = end($versions);
        return $latest;
    }

    public function get_package_info($current_revision, $offset)
    {
        $versions = $this->get_versions();
        $found = false;
        $skip_update = ($offset != 0);
        foreach ($versions as $v) {
            if ($found == false && $v['revision'] == $current_revision) {
                $found = true;
                if ($offset == 0) {
                    return $v;
                }
                $offset -= 1;
                continue;
            }
            if ($found) {
                if ($skip_update && isset($v['skip']) && $v['skip']) continue;
                if ($offset == 0) {
                    return $v;
                }
                $offset -= 1;
            }
        }
        return false;
    }

    public function download_package($current_revision, $offset)
    {
        $package_info = $this->get_package_info($current_revision, $offset);
        if (!$package_info) {
            return false;
        }
        foreach (array('file', 'checksum', 'signature') as $key) {
            if (!isset($package_info[$key])) {
                throw new ErrorException("Missing '$key' in package info");
            }
        }
        $filename = EPESI == 'EPESI' ? 'epesi' : 'update';
        $package_file = "$filename-$package_info[version]-$package_info[revision].ei.zip";
        $file_exists = file_exists($package_file);
        if (!$file_exists) {
            self::download($package_info['file'], $package_file);
        }
        $package_checksum = sha1_file($package_file);
        if ($package_info['checksum'] != $package_checksum) {
            throw new ErrorException("Checksum mismatch. Downloaded=$package_checksum, should be=$package_info[checksum]");
        }

        $verify_status = openssl_verify(file_get_contents($package_file), base64_decode($package_info['signature']), self::get_public_key(), OPENSSL_ALGO_SHA256);
        if ($verify_status === 0) {
            throw new ErrorException("Signature incorrect");
        }
        if ($verify_status === -1) {
            $error_string = openssl_error_string();
            if (!$error_string) {
                $error_string = 'no error string reported';
            }
            throw new ErrorException("Signature verify error: " . $error_string);
        }
        return new EpesiUpdatePackage($package_file);
    }

    public function get_update_package_info($current_revision)
    {
        $package_info = $this->get_package_info($current_revision, 1);
        return $package_info;
    }

    public function get_update_package($current_revision)
    {
        return $this->download_package($current_revision, 1);
    }

    public function get_current_package($current_revision)
    {
        return $this->download_package($current_revision, 0);
    }


    public static function get_public_key()
    {
        $PUBLIC_KEY = <<<KEY_WRAP
        -----BEGIN PUBLIC KEY-----
        MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvWFAZMVAGr3fPNK0v9Vt
        IErRSpl4I3wIWTr1kybpY8/+j9IX/t9qLvOPY4OVrkzURmKvS+VbSU8MSYZz9QIL
        TJUmNYkkyqJieSpQCq/7x5J2it+i1TeGKk8m3sOpL17+NUa/1e8a4W6FmLl9hwLd
        8TQnlVQJIva3JXA46S1E3BNPbaWdQrwABs5xGUterE890+rW63/pgD1pT1qEbmif
        oiTuG+dyhCo+REcjo8YWIpi+8BNJoo3Nn7Xxi71yA2Ps8DElIjjNRa/ca5A6SE61
        euoJaHtTSc7OsuDug2Rv0aQkvR7OmFyfIJAdAasZHWePWeuezlKJAAcvNFdjZ0Zw
        KwIDAQAB
        -----END PUBLIC KEY-----
        KEY_WRAP;
        return $PUBLIC_KEY;
    }

    public static function download($fileurl, $file = null) {
        $ch = curl_init($fileurl);

        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($file) {
            $f = fopen($file, 'w');
            if ($f === false) {
                throw new ErrorException("Cannot write into file: $file");
            }
            curl_setopt($ch, CURLOPT_FILE, $f);
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }

        $output = curl_exec($ch);
        $error_message = curl_error($ch);

        curl_close($ch);
        if ($file) {
            fclose($f);
        }
        if ($error_message != '') throw new ErrorException($error_message);
        return $output;
    }

    public static function check_system_requirements()
    {
        if (!class_exists('ZipArchive')) {
            throw new ErrorException('Missing zip extension');
        }
        if (!function_exists('curl_init')) {
            throw new ErrorException('Missing cURL extension');
        }
    }

}

class EpesiUpdatePackage
{
    private $zip;

    public function __construct(private $file)
    {
        $this->zip = new ZipArchive();
        $status = $this->zip->open($this->file);
        if ($status !== true) {
            throw new ErrorException(__('Zip %s open error: %s', array($this->file,$status)));
        }
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    public function delete()
    {
        @unlink($this->file);
    }

    public function get_file()
    {
        return $this->file;
    }

    public function files_not_writable()
    {
        $problems = array();
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            $f = './' . $stat['name'];
            if (file_exists($f)) {
                if (!is_writable($f)) {
                    $problems[] = $f;
                }
            } else {
                $up_f = basename($f);
                if (file_exists($up_f) && !is_writable($up_f)) {
                    $problems[] = $up_f;
                }
            }
        }
        return $problems;
    }

    public function files_modified()
    {
        $problems = array();
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            $f = './' . $stat['name'];
            if (file_exists($f)) {
                $system_file_checksum = sprintf("%u", hexdec(hash_file('crc32b', $f)));
                $archive_file_checksum = sprintf("%u", $stat['crc']);
                if ($system_file_checksum != $archive_file_checksum) {
                    $problems[] = $f;
                }
            }
        }
        return $problems;
    }

    public function extract()
    {
        $success = $this->zip->extractTo('./');
        return $success;
    }

    public function wipe() {
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            $f = './' . $stat['name'];
            if (file_exists($f) && !is_dir($f)) {
                unlink($f);
            }
        }
    }

    public function create_backup_of_modified_files()
    {
        $files = $this->files_modified();
        if (empty($files)) {
            return '';
        }

        $release_package = substr($this->file, 0, -4);
        do {
            $random_string = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 12);
            $backup_file = "modified_since_{$release_package}_{$random_string}.bkp.zip";
        } while (file_exists($backup_file));

        $b = new Backup($backup_file);
        $b->create($files, "Modified files backup");
        return $backup_file;
    }

}

// Standalone Smarty bootstrap for update.php - deliberately independent of
// the module/theme rendering pipeline, same reasoning as admin/AdminSmarty.php
// and SimpleLogin::render() (both docblocks explain why: this script must
// still work before the app is fully configured or logged in).
class UpdateSmarty
{
    private static $smarty;

    private static function instance()
    {
        if (!self::$smarty) {
            require_once('modules/Base/Theme/smarty/Smarty.class.php');
            $smarty = new Smarty();
            $smarty->template_dir = 'include/templates';
            $smarty->compile_dir = TEMP_DIR . '/Update/compiled/';
            $smarty->compile_id = 'update';
            if (!is_dir($smarty->compile_dir))
                mkdir($smarty->compile_dir, 0777, true);
            // Array callable, not a closure - see AdminSmarty::instance()'s
            // identical comment (Smarty 2 embeds this into the compiled
            // template file it caches to disk).
            $smarty->register_modifier('t', array(__CLASS__, 'translate'));
            self::$smarty = $smarty;
        }
        return self::$smarty;
    }

    static function translate($s)
    {
        return __($s);
    }

    static function render($template, array $vars = array())
    {
        $smarty = self::instance();
        foreach ($vars as $key => $value)
            $smarty->assign($key, $value);
        return $smarty->fetch($template);
    }
}

class EpesiUpdate
{
    public function run()
    {
        try {
            $this->load_epesi();
            if ($this->check_user()) {
                $this->handle_logout();
                $this->confirm_gate();
                if (!epesi_requires_update()) {
                    if ($this->handle_update_package() == false) {
                        $this->version_up_to_date();
                    }
                }
                $this->update_process();
            } else {
                $this->require_admin_login();
            }
        } catch (Exception $ex) {
            $this->quit($this->render_message('Exception occured: ' . $ex->getMessage()));
        }
    }

    protected function load_epesi()
    {
        $this->CLI = (php_sapi_name() == 'cli');
        if ($this->CLI) {
            global $argv;
            // allow to define DATA directory for CLI in argument
            if (isset($argv)) {
                define('EPESI_DIR','/');
                foreach (array_slice($argv, 1) as $x) {
                    if ($x == '-f') {
                        $this->cli_force_update = true;
                    } elseif ($x == '-b') {
                        $this->cli_create_backup = true;
                    } else {
                        define('DATA_DIR', $x);
                    }
                }
            }
        }

        define('CID', false);
        require_once 'include.php';
        require_once 'include/backups.php';

        // Enable all disabled modules
        //     check if method exists, because we recommend to use this update
        //     script on older installations where method was not present
        if (method_exists('ModuleManager', 'enable_modules')) {
            ModuleManager::enable_modules(ModuleManager::MODULE_NOT_FOUND);
        }

        ModuleManager::load_modules();
        Base_LangCommon::load();

        $this->system_version = Variable::get('version');
        $this->current_version = EPESI_VERSION;
        $this->current_revision = EPESI_REVISION;
    }

    protected function quit($msg)
    {
        if ($this->CLI) {
            die(strip_tags($msg) . "\n");
        } else {
            $this->body($msg);
            die();
        }
    }

    protected function check_user()
    {
        if ($this->CLI) {
            Base_AclCommon::set_sa_user();
            if (!Base_AclCommon::i_am_sa()) {
                $this->quit('No proper admin user.');
            }
            return true;
        }
        // Base_AclCommon::i_am_sa() unconditionally returns true whenever
        // the site's "anonymous_setup" convenience mode is on, regardless of
        // whether anyone is actually logged in - fine for browsing the app,
        // but this script can wipe and replace every core file, so it must
        // always require a REAL super-admin session, independent of that
        // mode. get_admin_level() hits the DB directly with no such bypass.
        return Base_AclCommon::is_user() && Base_AclCommon::get_admin_level() >= 2;
    }

    public function version_up_to_date()
    {
        $net_blocked = $this->net_update_blocked();
        if ($this->CLI) {
            if ($net_blocked) print (__('Network update has been blocked.') . "\n");
            print (__('Your %s does not require update', array(EPESI)) . "\n");
            print (__('Update procedure forced') . "\n");
        } else {
            $html = UpdateSmarty::render('update/up_to_date.tpl', array(
                'message' => __('Your %s does not require update', array(EPESI)),
                'net_blocked' => $net_blocked,
                'net_blocked_msg' => __('Network update has been blocked.'),
                'backups' => $this->backups_data(),
                'backups_label' => __('Backups'),
                'download_label' => __('Download'),
                'delete_label' => __('Delete'),
            ));
            $this->quit($html);
        }
    }

    // Returns backup rows for update/up_to_date.tpl - a delete request (?delete=)
    // is still handled here rather than in the template, since it's a
    // side-effecting action (unlink + redirect), not a view concern.
    protected function backups_data()
    {
        $files = array();
        foreach (glob('*.bkp.zip') as $f) $files[$f] = new Backup($f);

        if (isset($_GET['delete']) && isset($files[$_GET['delete']]) && file_exists($_GET['delete'])) {
            unlink($_GET['delete']);
            unset($files[$_GET['delete']]);
            $this->redirect(array());
        }

        uasort($files, fn(Backup $a, Backup $b) => $a->get_date() > $b->get_date());
        $rows = array();
        foreach ($files as $file => $backup) {
            $rows[] = array(
                'file' => $file,
                'date' => date('Y-m-d H:i:s', $backup->get_date()),
                'download_url' => urlencode($file),
                'delete_url' => '?' . http_build_query(array('delete' => $file)),
            );
        }
        return $rows;
    }

    // Renders update/message.tpl - the generic single-message view used by
    // most quit() call sites below (plain text, an optional bold heading
    // line, an optional follow-up button).
    protected function render_message($message, $link_href = null, $link_text = null, $heading = null)
    {
        return UpdateSmarty::render('update/message.tpl', array(
            'heading' => $heading,
            'message' => $message,
            'link_href' => $link_href,
            'link_text' => $link_text,
        ));
    }

    // Renders update/file_list.tpl - shared by both "files not writable" quit()
    // sites in handle_update_package() below and its "custom modifications" one.
    protected function render_file_list($heading, array $files, $link_href = null, $link_text = null)
    {
        return UpdateSmarty::render('update/file_list.tpl', array(
            'heading' => $heading,
            'files' => $files,
            'link_href' => $link_href,
            'link_text' => $link_text,
        ));
    }

    // Prints its own full standalone page (SimpleLogin::force_login_page(),
    // not this class's own body()/update_shell.tpl - that shell is for
    // already-authenticated update-progress screens) and exits directly,
    // rather than going through quit()/body().
    protected function require_admin_login()
    {
        // Real admin level, not i_am_sa() (see check_user() above) - under
        // anonymous_setup that always reads as "already super admin," which
        // would never log a real non-admin account out here.
        if (Base_AclCommon::is_user() && Base_AclCommon::get_admin_level() < 2) {
            Base_User_LoginCommon::logout();
        }
        // force_login_page(), not form(): form() can't produce a login form
        // at all once anonymous_setup is on (see check_user() above).
        print(SimpleLogin::force_login_page(EPESI . ' Update'));
        die();
    }

    // "Exit" on the confirm screen below - logs the current admin out and
    // redirects back to this same URL, so the next load shows the login form
    // again instead of re-running anything. CLI has no such button/session.
    protected function handle_logout()
    {
        if ($this->CLI || !isset($_GET['logout'])) {
            return;
        }
        Base_User_LoginCommon::logout();
        $this->redirect(array());
    }

    // One-time confirmation screen shown right after login, before this
    // script checks anything or touches a single file - the CLI is
    // unattended/automated by design and always skips it, same as
    // orphaned_modules_gate() below skips its own confirm step for CLI.
    // Persisted in $_SESSION (not a query param) so it isn't lost across the
    // rest of this class's own internal redirects/links.
    protected function confirm_gate()
    {
        if ($this->CLI || !empty($_SESSION['epesi_update_confirmed'])) {
            return;
        }
        if (isset($_GET['confirm'])) {
            $_SESSION['epesi_update_confirmed'] = 1;
            $this->redirect(array());
        }

        $html = UpdateSmarty::render('update/confirm.tpl', array(
            'message' => __('This utility will check for %s update. If new update is available it will download it and your instance will be updated automatically. Make sure you have a recent backup of your files and the database before proceeding further.', array(EPESI)),
            'check_url' => '?' . http_build_query(array('confirm' => 1)),
            'check_label' => __('Check for updates'),
            'exit_url' => '?' . http_build_query(array('logout' => 1)),
            'exit_label' => __('Exit'),
        ));
        $this->quit($html);
    }

    protected function cli_msg($msg)
    {
        if ($this->CLI) {
            print ($msg . "\n");
        }
    }

    protected function net_update_blocked()
    {
        return file_exists('.git') || file_exists('.noupdate');
    }

    protected function get_update_package()
    {
        if ($this->net_update_blocked()) {
            return false;
        }
        return EpesiPackageDownloader::instance()->get_update_package_info($this->current_revision);
    }

    protected function handle_update_package()
    {
        $update_package_info = $this->get_update_package();
        if ($update_package_info) {
            if (!$this->CLI && (TRIAL_MODE || DEMO_MODE)) {
                $this->quit($this->render_message(__('There is an update, but you don\'t have permissions to perform it. Please contact system administrator.')));
            }
            $latest_package_info = EpesiPackageDownloader::instance()->get_latest_package_info();
            $latest_version = $update_package_info['revision'] == $latest_package_info['revision'];
            $this->cli_msg("There is update package...");
            $action = false;
            if ($this->CLI) $action = 'get';
            if (isset($_GET['action'])) $action = $_GET['action'];
            if ($action) {
                $this->cli_msg("Downloading update package: $update_package_info[version]-$update_package_info[revision]...");
                $update_package = EpesiPackageDownloader::instance()->get_update_package($this->current_revision);

                $problems = $update_package->files_not_writable();
                if ($problems) {
                    $this->quit($this->render_file_list(__('Files not writable (please fix permissions)'), $problems));
                }

                $this->cli_msg("Downloading current release package...");
                $current_package = EpesiPackageDownloader::instance()->get_current_package($this->current_revision);
                if (!$current_package) {
                    throw new ErrorException('Cannot download current package');
                }

                $this->cli_msg("Looking for changes or permissions problems...");
                $problems = $current_package->files_not_writable();
                if ($problems) {
                    $this->quit($this->render_file_list(__('Files not writable (please fix permissions)'), $problems));
                }

                if ($this->CLI) {
                    $problems = $current_package->files_modified();
                    if ($problems) {
                        $this->cli_msg("Modified files:\n" . implode("\n", $problems));
                        if ($this->cli_create_backup) {
                            $this->cli_msg('Creating backup of modified files');
                            $backup_file = $current_package->create_backup_of_modified_files();
                            $this->cli_msg("Backup saved to: $backup_file");
                        }
                        if ($this->cli_force_update) {
                            $this->cli_msg('Update forced!');
                        } else {
                            $this->quit('Use -f switch to force update, -b to create backup of modified files. Both -f -b to backup and update');
                        }
                    }

                } else {
                    if ($action == 'update') {
                        // do nothing
                    } elseif ($action == 'backup') {
                        $backup_file = $current_package->create_backup_of_modified_files();
                        $this->quit($this->render_message(
                            __('Your backup is in the file: %s', array($backup_file)),
                            '?action=update', __('Update!'), __('Backup has been made')
                        ));
                    } else {
                        $problems = $current_package->files_modified();
                        if ($problems) {
                            $this->quit($this->render_file_list(
                                __('Files with custom modifications'), $problems,
                                '?action=backup', __('Backup modified files!')
                            ));
                        }
                    }
                }

                $this->turn_on_maintenance_mode();
                $this->cli_msg("Wipe current files...");
                $current_package->wipe();
                $this->cli_msg("Extract new files...");
                if ($update_package->extract()) {
                    $this->cli_msg("Delete package files...");
                    $current_package->delete();
                    if ($latest_version) {
                        $update_package->delete();
                    }
                    $this->cli_msg("Patches redirect...");
                    $this->redirect(array());
                } else {
                    $current_package->extract();
                    $this->quit($this->render_message(__('Extract error occured')));
                }
            } else {
                $html = UpdateSmarty::render('update/update_available.tpl', array(
                    'header' => __('Update package available to download!'),
                    'current_ver_label' => __('Your current %s version', array(EPESI)),
                    'version_with_revision' => "$this->current_version-$this->current_revision",
                    'package_label' => __('Update Package'),
                    'update_info' => "$update_package_info[version]-$update_package_info[revision]",
                    'warning_line1' => __('All core files will be replaced!'),
                    'warning_line2' => __('If you have changed any of those files, then we will backup them first.'),
                    'info_message' => __('Custom modules and your data will be preserved.'),
                    'download_label' => __('Download!'),
                ));
                $this->quit($html);
            }
        }
        return false;
    }

    protected function update_process()
    {
        @set_time_limit(0);

        // §59 — before running any patches, warn if the database references modules
        // whose code is not in this build (premium/custom left behind by a Core-only
        // package). No-op on a normal Core-only instance (empty list).
        $this->orphaned_modules_gate();

        // console
        if ($this->CLI) {
            PatchUtil::disable_time_management();
            $this->perform_update_start();
            $this->perform_update_patches(false);
            $this->perform_update_end();

            $update_package = $this->get_update_package();
            if ($update_package) $this->redirect(array());

            $this->quit(__('Done'));
        }

        // browser
        $up = & $_GET['up'];
        if ($up == 'start') {
            $this->perform_update_start();
            $this->redirect(array('up' => 'patches'));
        } elseif ($up == 'patches') {
            $success = $this->perform_update_patches();
            if ($success) {
                $this->redirect(array('up' => 'end'));
            }
        } elseif ($up == 'end') {
            $this->perform_update_end();
            $update_package = $this->get_update_package();
            $redirect = $update_package ? array() : get_epesi_url();
            $this->redirect($redirect);
        } else {
            $this->update_body();
        }
    }

    /**
     * §59 — premium/custom-module upgrade gate. If the database lists modules whose
     * code is missing from this build, warn the admin BEFORE any patch runs and make
     * them confirm. Fails open: an empty list (a normal Core-only instance) is a
     * complete no-op, so ordinary upgrades are untouched. CLI warns but proceeds
     * (expert/automated context — never trap a script). Their data is not deleted by
     * a Core update; it waits in the DB until the matching module is restored.
     */
    protected function orphaned_modules_gate()
    {
        if (!method_exists('ModuleManager', 'get_orphaned_modules')) {
            return; // older codebase being updated by this script
        }
        $orphaned = ModuleManager::get_orphaned_modules();
        if (!$orphaned) {
            return; // common case: Core-only instance
        }

        // Admin already acknowledged in this session (or via the confirm link).
        if (isset($_GET['confirm_orphaned'])) {
            $_SESSION['epesi_orphaned_confirmed'] = 1;
            if (!$this->CLI) {
                $this->redirect(array()); // reload clean, then fall through next time
            }
            return;
        }
        if (!empty($_SESSION['epesi_orphaned_confirmed'])) {
            return;
        }

        if ($this->CLI) {
            $this->cli_msg('WARNING: the database lists modules with no code in this build:');
            foreach ($orphaned as $m) $this->cli_msg('   - ' . $m);
            $this->cli_msg('These are most likely premium/custom modules. Migrate them to this');
            $this->cli_msg('version together with the core. Their data stays in the database.');
            $this->cli_msg('Continuing update...');
            return;
        }

        $html = UpdateSmarty::render('update/orphaned_modules.tpl', array(
            'heading' => __('Additional modules detected'),
            'intro' => __('The following modules are installed on this system but their code is not part of this %s package:', array(EPESI)),
            'modules' => $orphaned,
            'warning' => __('These are most likely premium or custom modules. Updating the core on its own may leave them non-functional until they are migrated to this version as well.'),
            'data_note' => __('Your data is not deleted — it stays in the database until the matching module is restored. If you are not sure, please contact your %s provider before continuing.', array(EPESI)),
            'proceed_url' => '?' . http_build_query(array('confirm_orphaned' => 1)),
            'confirm_label' => __('I understand — continue the update anyway'),
        ));
        $this->quit($html);
    }

    protected function redirect($url_or_get)
    {
        if ($this->CLI) {
            global $argv;
            print("Redirect...\n");
            $args = array_slice($argv, 1);
            array_unshift($args, __FILE__);
            system((defined('PHP_BINARY')?PHP_BINARY:'php').' '.implode(' ', $args));
            exit();
        }
        if (is_string($url_or_get)) {
            $location = $url_or_get;
        } else {
            $location = $_SERVER['PHP_SELF'];
            if (count($url_or_get)) {
                $location .= '?' . http_build_query($url_or_get);
            }
        }
        header("Location: $location");
        die();
    }

    protected function update_body()
    {
        $help_url = htmlspecialchars(get_epesi_url() . '/docs/UPDATE.md');
        $help_link = '<a href="' . $help_url . '" target="_blank">' . __('help file') . '</a>';
        $html = UpdateSmarty::render('update/update_prompt.tpl', array(
            'update_msg' => __('Update %s from version %s to %s.', array(EPESI, $this->system_version, $this->current_version)),
            'do_not_close' => __('Please do not close this window until process will be fully finished.'),
            'info' => __('Your browser drives update process. For more information read %s', array($help_link)),
            'update_label' => __('Update!'),
        ));
        $this->quit($html);
    }

    protected function turn_on_maintenance_mode()
    {
        if (MaintenanceMode::is_on()) return;

        $msg = __('%s is currently updating. Please wait or contact your system administrator.', array(EPESI));
        if ($this->CLI) {
            MaintenanceMode::turn_on($msg);
        } else {
            MaintenanceMode::turn_on_with_cookie($msg);
        }
    }

    protected function perform_update_start()
    {
        $this->cli_msg("Update from " . $this->system_version . " to " . $this->current_version . "...");
        $this->turn_on_maintenance_mode();
        //restore innodb tables in case of db reimport
        $mysql = stripos(DATABASE_DRIVER, 'mysql') !== false;
        if ($mysql) {
            $tbls = DB::MetaTables('TABLE', true);
            foreach ($tbls as $t) {
                $tbl = DB::GetRow('SHOW CREATE TABLE ' . $t);
                if (!isset($tbl[1]) || preg_match('/ENGINE=myisam/i', $tbl[1])) {
                    DB::Execute('ALTER TABLE ' . $t . ' ENGINE = INNODB');
                }
            }
        }
    }

    protected function perform_update_patches($browser = true)
    {
        $this->turn_on_maintenance_mode();

        $patches = PatchUtil::apply_new(true);

        if ($browser) {
            $success = PatchUtil::all_successful($patches);
            if (!$success) {
                $msg = self::format_patches_msg($patches);
                $this->body($msg);
            }
            return $success;
        }
    }

    protected function format_patches_msg($patches)
    {
        $rows = array();
        /** @var Patch $patch */
        foreach ($patches as $patch) {
            // show only awaiting or processed one
            if ($patch->get_apply_status() == Patch::STATUS_SUCCESS) {
                continue;
            }
            $rows[] = array(
                'module' => $patch->get_module(),
                'description' => $patch->get_short_description(),
                'timeout' => $patch->get_apply_status() == Patch::STATUS_TIMEOUT,
                'user_message' => $patch->get_user_message(),
            );
        }
        return UpdateSmarty::render('update/patches_progress.tpl', array(
            'heading' => __('Patches to apply'),
            'last_refresh_label' => __('Last refresh'),
            'last_refresh' => date('Y-m-d H:i:s'),
            'module_label' => __('Module'),
            'patch_label' => __('Patch'),
            'status_label' => __('Status'),
            'pending_label' => __('pending'),
            'rows' => $rows,
        ));
    }

    protected function perform_update_end()
    {
        $this->turn_on_maintenance_mode();

        Base_ThemeCommon::themeup();
        ModuleManager::create_load_priority_array();

        Variable::set('version', EPESI_VERSION);
        MaintenanceMode::turn_off();

        $this->cli_msg("Updated to " . $this->current_version);
    }

    protected function body($html)
    {
        print(UpdateSmarty::render('update_shell.tpl', array(
            'title' => EPESI . ' Update',
            'body' => $html,
        )));
    }

    protected $CLI;
    protected $cli_force_update = false;
    protected $cli_create_backup = false;

    protected $system_version;
    protected $current_version;
    protected $current_revision;
}

$x = new EpesiUpdate();
$x->run();
