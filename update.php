<?php
/**
 * EPESI Core updater.
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
        $PUBLIC_KEY = <<<KEY
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvWFAZMVAGr3fPNK0v9Vt
IErRSpl4I3wIWTr1kybpY8/+j9IX/t9qLvOPY4OVrkzURmKvS+VbSU8MSYZz9QIL
TJUmNYkkyqJieSpQCq/7x5J2it+i1TeGKk8m3sOpL17+NUa/1e8a4W6FmLl9hwLd
8TQnlVQJIva3JXA46S1E3BNPbaWdQrwABs5xGUterE890+rW63/pgD1pT1qEbmif
oiTuG+dyhCo+REcjo8YWIpi+8BNJoo3Nn7Xxi71yA2Ps8DElIjjNRa/ca5A6SE61
euoJaHtTSc7OsuDug2Rv0aQkvR7OmFyfIJAdAasZHWePWeuezlKJAAcvNFdjZ0Zw
KwIDAQAB
-----END PUBLIC KEY-----
KEY;
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
    private $file;
    private $zip;

    public function __construct($file)
    {
        $this->file = $file;
        $this->zip = new ZipArchive();
        $status = $this->zip->open($file);
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

class EpesiUpdate
{
    public function run()
    {
        try {
            $this->load_epesi();
            if ($this->check_user()) {
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
            $this->quit('Exception occured: ' . $ex->getMessage());
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
        }
        return Base_AclCommon::i_am_sa();
    }

    public function version_up_to_date()
    {
        $net_blocked = $this->net_update_blocked();
        $net_blocked_msg = __('Network update has been blocked.');
        $msg = __('Your %s does not require update', array(EPESI));
        if ($this->CLI) {
            if ($net_blocked) print ($net_blocked_msg . "\n");
            print ($msg . "\n");
            print (__('Update procedure forced') . "\n");
        } else {
            if ($net_blocked) $msg .= '<br><br>' . $net_blocked_msg;
            $msg .= $this->saved_backups_list();
            $this->quit($msg);
        }
    }

    public function saved_backups_list()
    {
        $files = array();
        foreach (glob('*.bkp.zip') as $f) $files[$f] = new Backup($f);

        if (isset($_GET['delete']) && isset($files[$_GET['delete']]) && file_exists($_GET['delete'])) {
            unlink($_GET['delete']);
            unset($files[$_GET['delete']]);
            $this->redirect(array());
        }

        uasort($files, function(Backup $a, Backup $b) { return $a->get_date() > $b->get_date();});
        $backups = '';
        foreach ($files as $file => $backup) {
            $download_url = urlencode($file);
            $download = "<a target=\"_blank\" href=\"$download_url\">[" . __('Download') . "]</a>";
            $delete_href = '?' . http_build_query(array('delete' => $file));
            $delete = "<a href=\"$delete_href\">[" . __('Delete') . "]</a>";
            $description = date("Y-m-d H:i:s", $backup->get_date()) . " - $file $download $delete";
            $backups .= "$description<br>";
        }
        $ret = '';
        if ($backups) {
            $ret = "<h3>" . __('Backups') . "</h3><p>$backups</p>";
        }
        return $ret;
    }

    protected function require_admin_login()
    {
        $msg = '<p><strong>' . __("You need to be logged in as super admin use this script.") . '</strong></p>';
        $msg .= $this->login_form();
        $this->quit($msg);
    }

    protected function login_form()
    {
        if (Base_AclCommon::i_am_user() && !Base_AclCommon::i_am_sa()) {
            Base_User_LoginCommon::logout();
        }
        $form = SimpleLogin::form();
        return "<p>$form</p>";
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
                $this->quit(__('There is an update, but you don\'t have permissions to perform it. Please contact system administrator.'));
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
                    $this->quit('<p><strong>' . __('Files not writable (please fix permissions)') . ':</strong></p>'."\n" . implode("<br>\n", $problems));
                }

                $this->cli_msg("Downloading current release package...");
                $current_package = EpesiPackageDownloader::instance()->get_current_package($this->current_revision);
                if (!$current_package) {
                    throw new ErrorException('Cannot download current package');
                }

                $this->cli_msg("Looking for changes or permissions problems...");
                $problems = $current_package->files_not_writable();
                if ($problems) {
                    $this->quit('<p><strong>' . __('Files not writable (please fix permissions)') . ':</strong></p>'."\n" . implode("<br>\n", $problems));
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
                        $backup_msg = '<p><strong>' . __('Backup has been made') . '</strong></p>' . "\n";
                        $backup_msg .= '<br>' . '<p>' . __('Your backup is in the file: %s', array($backup_file)) . "</p>\n";
                        $backup_msg .= '<br>' . '<p><a class="button" href="?action=update">' . __('Update!') . '</a></p>';
                        $this->quit($backup_msg);
                    } else {
                        $problems = $current_package->files_modified();
                        if ($problems) {
                            $create_backup_msg = '<p><strong>' . __('Files with custom modifications') . ':</strong></p>' . "\n" . implode("<br>\n", $problems);
                            $create_backup_msg .= '<br>' . '<p><a class="button" href="?action=backup">' . __('Backup modified files!') . '</a></p>';
                            $this->quit($create_backup_msg);
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
                    $this->quit(__('Extract error occured'));
                }
            } else {
                $header = __('Update package available to download!');
                $version_with_revision = "$this->current_version-$this->current_revision";
                $update_info = "$update_package_info[version]-$update_package_info[revision]";
                $current_ver = __('Your current %s version', array(EPESI)) . ': <strong>' . $version_with_revision . '</strong>';
                $text_p = __('Update Package') . ': <strong>' . $update_info . '</strong>';
                $warning_message = __('All core files will be replaced!') . '<br/><br/>'
                                   . __('If you have changed any of those files, then we will backup them first.');
                $info_message = __('Custom modules and your data will be preserved.');
                $msg = "<p><strong>$header</strong></p><p>$current_ver</p><p>$text_p</p>";
                $msg .= "<p style=\"color: red; font-weight: bold\">$warning_message</p>";
                $msg .= "<p style=\"font-weight: bold\">$info_message</p>";
                $msg .= '<p><a class="button" href="?action=get">' . __('Download!') . '</a></p>';
                $this->quit($msg);
            }
        }
        return false;
    }

    protected function update_process()
    {
        @set_time_limit(0);

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

    protected function update_msg()
    {
        $msg = __('Update %s from version %s to %s.', array(EPESI, $this->system_version, $this->current_version));
        return "<p>$msg</p>";
    }

    protected function update_process_info_msg()
    {
        $do_not_close = __('Please do not close this window until process will be fully finished.');
        $url_text = __('help file');
        $url = get_epesi_url() . '/docs/UPDATE.md';
        $url = htmlspecialchars($url);
        $link = "<a href=\"$url\" target=\"_blank\">$url_text</a>";
        $info = __('Your browser drives update process. For more information read %s', array($link));

        $msg = "<p><strong>$do_not_close</strong></p><p>$info</p>";
        return "$msg";
    }

    protected function update_body()
    {
        $msg = $this->update_msg();
        $msg .= $this->update_process_info_msg();
        $msg .= ' <a class="button" href="?up=start">' . __('Update!') . '</a>';
        $this->quit($msg);
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
        $msg = "<h1>" . __('Patches to apply') . ":</h1>";
        $msg .= "<p>" . __('Last refresh') . ' - ' . date('Y-m-d H:i:s') . "</p>";
        $msg .= '<table>';
        // table header
        $format = "<tr><th>%s</th><th>%s</th><th>%s</th></tr>\n";
        $msg .= sprintf($format, __('Module'), __('Patch'), __('Status'));

        $format = "<tr><td>%s</td><td>%s</td><td style=\"text-align: center; font-size: 0.8em; color: gray\">%s</td></tr>\n";
        /** @var Patch $patch */
        foreach ($patches as $patch) {
            // show only awaiting or processed one
            if ($patch->get_apply_status() == Patch::STATUS_SUCCESS) {
                continue;
            }
            $status = __('pending');
            if ($patch->get_apply_status() == Patch::STATUS_TIMEOUT) {
                $status = '<img src="images/loader.gif" alt="Processing..." width="128" height="5" border="0">';
            }
            if (($user_message = $patch->get_user_message()) != null) {
                $status .= "<div>$user_message</div>";
            }
            $msg .= sprintf($format, $patch->get_module(), $patch->get_short_description(), $status);
        }
        $msg .= '</table>';
        $msg .= '<script type="text/javascript">location.reload(true)</script>';
        return $msg;
    }

    protected function perform_update_end()
    {
        $this->turn_on_maintenance_mode();

        Base_ThemeCommon::themeup();
        Base_LangCommon::update_translations();
        ModuleManager::create_load_priority_array();

        Variable::set('version', EPESI_VERSION);
        MaintenanceMode::turn_off();

        $this->cli_msg("Updated to " . $this->current_version);
    }

    protected function body($html)
    {
        ?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
        <html>
        <head>
            <meta content="text/html; charset=utf-8" http-equiv="content-type">
            <title><?php print(EPESI); ?> update</title>
            <link href="setup.css" type="text/css" rel="stylesheet"/>
            <meta name="robots" content="NOINDEX, NOARCHIVE">
        </head>
        <body>
        <table id="banner" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td class="image">&nbsp;</td>
                <td class="back">&nbsp;</td>
            </tr>
        </table>
        <br>
        <center>
            <table id="main" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <center>
                        <?php print $html; ?>
                        </center>
                    </td>
                </tr>
            </table>
        </center>
        <br>
        <center>
            <span class="footer">Copyright &copy; 2016 &bull; <a
                    href="http://www.telaxus.com">Telaxus LLC</a></span>
            <br>

            <p><a href="http://www.epesi.org"><img
                        src="images/epesi-powered.png" alt="image"
                        border="0"></a></p>
        </center>
        </body>
        </html>
    <?php
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
