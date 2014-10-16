<?php
/**
 * EPESI Core updater.
 *
 * @author    Adam Bukowski <abukowski@telaxus.com>
 * @version   2.0
 * @copyright Copyright &copy; 2014, Telaxus LLC
 * @license   MIT
 * @package   epesi-base
 */
defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);

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
            throw new ErrorException(__('Zip open error: %s', array($status)));
        }
    }

    public function __destruct()
    {
        $this->zip->close();
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

    public function extract()
    {
        $success = $this->zip->extractTo('./');
        return $success;
    }

    public static function system_requirements()
    {
        return class_exists('ZipArchive');
    }

    public static function package()
    {
        if (!self::system_requirements()) {
            return false;
        }

        $possible_files = glob('epesi-*.ei.zip');
        if (count($possible_files)) {
            rsort($possible_files);
            return new self($possible_files[0]);
        }
        return false;
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
            die($msg . "\n");
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
        $this->quit($msg);
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
        if ($this->CLI) {
            return false;
        }
        $package = EpesiUpdatePackage::package();
        if ($package) {
            $action = & $_GET['package'];
            if ($action) {
                $files_not_writable = $package->files_not_writable();
                if (empty($files_not_writable)) {
                    $this->turn_on_maintenance_mode();
                    if ($package->extract()) {
                        $this->redirect(array());
                    } else {
                        $this->quit(__('Extract error occured'));
                    }
                } else {
                    $msg = '<p><strong>' . __('Files with bad permissions:') . '</strong></p>';
                    foreach ($files_not_writable as $file) {
                        $msg .= "$file</br>";
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
        $msg = __('EPESI is currently updating. Please wait or contact your system administrator.');
        MaintenanceMode::turn_on_with_cookie($msg);
    }

    protected function perform_update_start()
    {
        $this->turn_on_maintenance_mode();
        //restore innodb tables in case of db reimport
        if (DB::is_mysql()) {
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
        Base_ThemeCommon::themeup();
        Base_LangCommon::update_translations();
        ModuleManager::create_load_priority_array();

        Variable::set('version', EPESI_VERSION);
        MaintenanceMode::turn_off();
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

if(isset($argv)) {
    define('EPESI_DIR','/');
    if (isset($argv[1])) {
        define('DATA_DIR', $argv[1]);
    }
}

$x = new EpesiUpdate();
$x->run();
