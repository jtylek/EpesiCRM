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
                if (sprintf("%u",hexdec(hash_file('crc32b',$f)))!=sprintf("%u",$stat['crc'])) {
                    $problems[] = $f.' file modified: CRC local='.hexdec(hash_file('crc32b',$f)).' zip='.sprintf("%u",$stat['crc']);
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

    public static function system_requirements()
    {
        return class_exists('ZipArchive') && function_exists('curl_init');
    }

    public static function get_update_package($current_version)
    {
        if (!self::system_requirements()) {
            return false;
        }

        $package = self::download_package($current_version,1);
        if(!$package) return false;

        return new self($package);
    }

    public static function get_current_package($current_version)
    {
        if (!self::system_requirements()) {
            return false;
        }

        $package = self::download_package($current_version,0);
        if(!$package) return false;

        return new self($package);
    }

    private static function download_package($current_version,$offset) {
        static $versions = null;
        if(!isset($versions)) {
            if(!self::download('http://ess.epe.si/update.json','update.json')) return false;
            $versions_tmp = @json_decode(file_get_contents('update.json'));
            @unlink('update.json');
            if(!$versions_tmp || !isset($versions_tmp->files)) return false;
            $versions_tmp = (array)$versions_tmp->files;
            $versions = array();
            foreach($versions_tmp as $v) $versions[$v->version] = (array)$v;
        }
        if(!is_array($versions) || !isset($versions[$current_version])) return false;

        $original_keys = array_keys($versions);
        $keys = array_flip($original_keys);
        $values = array_values($versions);
        if(!isset($values[$keys[$current_version]+$offset])) return false; //no update available, already latest version
        
        $update = $values[$keys[$current_version]+$offset];
        if(!isset($update['file']) || !isset($update['checksum']) || !isset($update['signature'])) return false;
        $tmpfname = 'epesi-'.$original_keys[$keys[$current_version]+$offset].'.ei.zip';
        if(!file_exists($tmpfname) && !self::download($update['file'],$tmpfname)) return false;

        if ($update['checksum']!=sha1_file($tmpfname)) {
            unlink($tmpfname);
            return false;
        }

        global $PUBLIC_KEY;
        $verify_status = openssl_verify(file_get_contents($tmpfname), base64_decode($update['signature']), $PUBLIC_KEY, OPENSSL_ALGO_SHA256);
        if ($verify_status !== 1) {
            if($this->CLI) print("Signature error: $tmpfname\n");
            return false;
        }
        return $tmpfname;
    }

    private static function download($fileurl,$filename) {
        $err_msg = '';
        $ch = curl_init($fileurl);

        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);
        $errno = curl_error($ch);

        curl_close($ch);
        if($errno!='') return false;
        file_put_contents($filename,$output);
        return true;
    }
}

class EpesiUpdate
{
    public function run()
    {
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
    }

    protected function load_epesi()
    {
        $this->CLI = (php_sapi_name() == 'cli');
        if ($this->CLI) {
            // allow to define DATA directory for CLI in argument
            if(isset($argv)) {
                define('EPESI_DIR','/');
                if (isset($argv[1])) {
                    define('DATA_DIR', $argv[1]);
                }
            }
        }

        define('CID', false);
        require_once('include.php');
        ModuleManager::load_modules();
        Base_LangCommon::load();

        $this->system_version = Variable::get('version');
        $this->current_version = EPESI_VERSION;
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
        $msg = __('Your EPESI does not require update');
        if ($this->CLI) {
            print ($msg . "\n");
            print (__('Update procedure forced') . "\n");
        } else {
            $this->quit($msg);
        }
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

    protected function handle_update_package()
    {
        //if ($this->CLI) {
        //    return false;
        //}
        $package = EpesiUpdatePackage::get_update_package($this->system_version);
        if ($package) {
            $action = ($this->CLI || (isset($_GET['package']) && $_GET['package']));
            if ($action) {
                if($this->CLI) print("There is update package...\n");
                $problems = $package->files_not_writable();
                if (empty($problems)) {
                    $current_package = EpesiUpdatePackage::get_current_package($this->system_version);
                    if($this->CLI) print("Looking for changes or permissions problems...\n");
                    $problems = $current_package->files_modified();
                    if(empty($problems)) $problems = $current_package->files_not_writable();
                    
                    if(empty($problems)) {
                        $this->turn_on_maintenance_mode();
                        if($this->CLI) print("Wipe current files...\n");
                        $current_package->wipe();
                        if($this->CLI) print("Extract new files...\n");
                        if ($package->extract()) {
                            if($this->CLI) print("Delete package files...\n");
                            $current_package->delete();
                            $package->delete();
                            if($this->CLI) print("Patches redirect...\n");
                            $this->redirect(array());
                        } else {
                            $current_package->extract();
                            $this->quit(__('Extract error occured'));
                        }
                    }
                }
                if($problems) {
                    $msg = '<p><strong>' . __('Files with bad permissions or modified:') . '</strong></p>'."\n";
                    foreach ($problems as $file) {
                        $msg .= "$file</br>\n";
                    }
                    $this->quit($msg);
                }
            } else {
                $header = __('Easy update package found!');
                $current_ver = __('Your current EPESI version') . ': <strong>' . $this->current_version . '</strong>';
                $text_p = __('Package') . ': <strong>' . $package->get_file() . '</strong>';
                $warning_message = __('All core files will be overwritten!') . '<br/><br/>'
                                   . __('If you have changed any of those files, then all custom modifications will be lost.');
                $info_message = __('Custom modules and your data will be preserved.');
                $msg = "<p><strong>$header</strong></p><p>$current_ver</p><p>$text_p</p>";
                $msg .= "<p style=\"color: red; font-weight: bold\">$warning_message</p>";
                $msg .= "<p style=\"font-weight: bold\">$info_message</p>";
                $msg .= '<p><a class="button" href="?package=extract">' . __('Extract!') . '</a></p>';
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

            $package = EpesiUpdatePackage::get_update_package(EPESI_VERSION);
            if($package) $this->redirect(array());

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
            $this->redirect(get_epesi_url());
        } else {
            $this->update_body();
        }
    }

    protected function redirect($url_or_get)
    {
        if($this->CLI) {
            print("Redirect...\n");
            system((defined('PHP_BINARY')?PHP_BINARY:'php').' '.__FILE__);
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
        $msg = __('Update EPESI from version %s to %s.', array($this->system_version, $this->current_version));
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

        $msg = __('EPESI is currently updating. Please wait or contact your system administrator.');
        if ($this->CLI) {
            MaintenanceMode::turn_on($msg);
        } else {
            MaintenanceMode::turn_on_with_cookie($msg);
        }
    }

    protected function perform_update_start()
    {
        if($this->CLI) print("Update from ".$this->system_version." to ".$this->current_version."...\n");
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

        if($this->CLI) print("Updated to ".$this->current_version."\n");
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
            <span class="footer">Copyright &copy; 2014 &bull; <a
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
    protected $system_version;
    protected $current_version;
}

$x = new EpesiUpdate();
$x->run();
