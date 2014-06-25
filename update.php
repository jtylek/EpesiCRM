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

class EpesiUpdate
{
    public function run()
    {
        $this->load_epesi();
        if (!epesi_requires_update()) {
            $this->version_up_to_date();
        }
        if ($this->check_user()) {
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
            die($msg);
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
        $msg = $this->update_msg();
        $msg .= '<p><strong>' . __("You need to be logged in as super admin to perform update.") . '</strong></p>';
        $msg .= $this->login_form();
        $this->quit($msg);
    }

    protected function login_form()
    {
        if (Base_AclCommon::i_am_user() && !Base_AclCommon::i_am_sa()) {
            Base_User_LoginCommon::logout();
        }
        require_once 'admin/Authorization.php';
        $form = AdminAuthorization::form();
        return "<p>$form</p>";
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
            $this->quit(__('Done') . "\n");
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
            $location = $_SERVER['PHP_SELF'] . '?' . http_build_query($url_or_get);
        }
        header("Location: $location");
        die();
    }

    protected function update_msg()
    {
        $msg = __('Updating EPESI from version %s to %s. This operation may take several minutes.', array($this->system_version, $this->current_version));
        return "<p>$msg</p>";
    }

    protected function update_process_info_msg()
    {
        $do_not_close = __('Please do not close this window until process is fully finished.');
        $url_text = __('help file');
        $url = get_epesi_url() . '/docs/CHANGELOG.md';
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
        $msg .= ' <a href="?up=start">' . __('Click here to proceed') . '</a>';
        $this->quit($msg);
    }

    protected function perform_update_start()
    {
        $msg = __('EPESI is currently updating. Please wait or contact your system administrator.');
        MaintenanceMode::turn_on_with_cookie($msg);
        //restore innodb tables in case of db reimport
        if (strcasecmp(DATABASE_DRIVER, "postgres") !== 0) {
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
            $success = 0;
            $total = count($patches);
            /** @var Patch $p */
            foreach ($patches as $p) {
                if ($p->get_apply_status() == Patch::STATUS_SUCCESS) {
                    $success += 1;
                } else {
                    break;
                }
            }
            $ret = ($success == $total);
            if (!$ret) {
                $msg = "<p>" . date('Y-m-d H:i:s') . "</p>";
                $msg .= "<p>" . __('Patches') . ": $success / $total </p>";
                $msg .= "<p>" . __('Still working...') . "</p>";
                $msg .= '<script type="text/javascript">location.reload()</script>';
                $this->body($msg);
            }
            return $ret;
        }
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
                        <?php print $html; ?>
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
