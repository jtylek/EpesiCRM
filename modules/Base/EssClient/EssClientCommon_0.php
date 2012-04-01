<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Telaxus LLC
 * @license MIT
 * @version 20111207
 * @package epesi-Base
 * @subpackage EssClient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EssClientCommon extends Base_AdminModuleCommon {

    const VAR_LICENSE_KEY = 'license_key';
    const VAR_INSTALLATION_STATUS = 'ess_installations_status';

    public static function menu() {
        if (!Base_AclCommon::i_am_sa())
            return;
        $text = 'EPESI registration';
        if (!self::get_license_key()) {
            $text = 'Register EPESI!';
        }
        return array('Help' => array('__submenu__' => 1, $text => array()));
    }

    public static function get_server_url() {
        return 'https://ess.epe.si/';
    }

    public static function get_payments_url() {
        return 'https://ess.epe.si/payments/';
    }

    /**
     * Get first(by id) user that is super administrator and get it's first
     * and last name from crm_contacts
     *
     * This function uses DB query to get users and generally it should be
     * easier way to get super admin.
     *
     * @return array with keys admin_email, admin_first_name, admin_last_name
     */
    public static function get_possible_admin() {
        $users = DB::GetAll('select id, mail from user_login inner join user_password on user_login_id = id');
        foreach ($users as $u) {
            if (Base_AclCommon::is_user_in_group(
                            Base_AclCommon::get_acl_user_id($u['id']), 'Super administrator')) {
                $x = array('admin_email' => $u['mail']);
                if (ModuleManager::is_installed('CRM_Contacts') > -1) {
                    $contact = CRM_ContactsCommon::get_contact_by_user_id($u['id']);
                    if ($contact) {
                        $x['admin_first_name'] = $contact['first_name'];
                        $x['admin_last_name'] = $contact['last_name'];
                    }
                }
                return $x;
            }
        }
        return null;
    }

    public static function is_registered() {
        $status = self::get_installation_status();
        if (strpos($status, 'confirmed') !== false
                || $status == 'validated')
            return true;
        return false;
    }

    public static function get_installation_status($clear_cache = true) {
        $status = $clear_cache === false ? Variable::get(self::VAR_INSTALLATION_STATUS, false) : null;
        if (!$status && self::has_license_key()) {
            $status = self::server()->installation_status();
            Variable::set(self::VAR_INSTALLATION_STATUS, $status);
        }
        return $status;
    }

    public static function has_license_key() {
        return self::get_license_key() != false;
    }

    public static function get_license_key() {
        $ret = Variable::get(self::VAR_LICENSE_KEY, false);
        if (is_array($ret)) {
            $serv = self::get_server_url();
            $key = '';
            if (isset($ret[$serv]))
                $key = $ret[$serv];
            return $key;
        }
        return $ret;
    }

    public static function set_license_key($license_key) {
        $keys = Variable::get(self::VAR_LICENSE_KEY, false);
        if ($keys) {
            if (is_array($keys)) {
                $keys[self::get_server_url()] = $license_key;
            } else {
                $keys = array(self::get_server_url() => $license_key);
            }
        } else {
            $keys = array(self::get_server_url() => $license_key);
        }
        return Variable::set(self::VAR_LICENSE_KEY, $keys);
    }

    public static function clear_license_key($only_current = true) {
        $license_keys = Variable::get(self::VAR_LICENSE_KEY, false);
        if (!$only_current || !is_array($license_keys)) {
            Variable::delete(self::VAR_LICENSE_KEY, false);
            return;
        }
        unset($license_keys[self::get_server_url()]);
        Variable::set(self::VAR_LICENSE_KEY, $license_keys);
    }

    /** @var IClient */
    protected static $client_requester = null;

    /**
     * Get server connection object to perform requests
     * @param boolean $recreate_object force to recreate object
     * @return ClientRequester server requester
     */
    public static function server($recreate_object = false) {
        if (self::$client_requester == null || $recreate_object == true) {
            // include php file
            $dir = self::Instance()->get_module_dir();
            require_once $dir . 'ClientRequester.php';
            // create object
            self::$client_requester = new ClientRequester(self::get_server_url());
            self::$client_requester->set_client_license_key(self::get_license_key());
        }
        return self::$client_requester;
    }

    public static function admin_access() {
        return Base_AclCommon::i_am_sa();
    }

    public static function admin_caption() {
        return "EPESI Registration";
    }

    public static function get_support_email() {
        $email = 'bugs@telaxus.com'; // FIXME
        if (ModuleManager::is_installed('CRM_Roundcube') >= 0) {
            $email = CRM_RoundcubeCommon::get_mailto_link($email);
        } else {
            $email = '<a href="mailto:' . $email . '">' . $email . '</a>';
        }
        return $email;
    }

    public static function add_client_message_error($message) {
        self::add_client_messages(array(array(), array(), array($message)));
    }

    public static function add_client_message_warning($message) {
        self::add_client_messages(array(array(), array($message), array()));
    }

    public static function add_client_message_info($message) {
        self::add_client_messages(array(array($message), array(), array()));
    }

    /**
     * Add client messages
     * @param array $messages Array of arrays in order info, warning, error
     */
    public static function add_client_messages($messages) {
        $msgs = Module::static_get_module_variable('Base/EssClient', 'messages', array(array(), array(), array()));
        foreach ($msgs as $k => &$v) {
            $v = array_merge($v, $messages[$k]);
            $v = array_unique($v);
        }
        Module::static_set_module_variable('Base/EssClient', 'messages', $msgs);
    }

    public static function client_messages_frame($load_by_js = true) {
        $content = $load_by_js ? '' : self::format_client_messages();
        $buttons = '';
        if ($load_by_js) {
            self::client_messages_load_by_js();
            $hide_all = Base_LangCommon::ts('Base/EssClient', 'Hide messages');
            $show_discarded = Base_LangCommon::ts('Base/EssClient', 'Show discarded');
            $buttons .= "<div class=\"button\" id=\"client_messages_frame_hide\">$hide_all</div>";
            $buttons .= "<div class=\"button\" id=\"client_messages_frame_show_discarded\">$show_discarded</div>";
        }
        return '<div id="client_messages_frame"><div id="client_messages_frame_content">' . $content . '</div>' . $buttons . '</div>';
    }

    public static function client_messages_load_by_js() {
        load_js(dirname(__FILE__) . '/messages_hiding.js');
        eval_js('$("client_messages_frame_content").innerHTML = ' . json_encode(self::format_client_messages()));
        eval_js('set_client_messages_frame_id("client_messages_frame");');
    }

    private static function format_client_messages($cleanup = true) {
        $msgs = Module::static_get_module_variable('Base/EssClient', 'messages', array(array(), array(), array()));

        $ret = self::format_messages_frame('#FFCCCC', 'Error messages:', $msgs[2])
                . self::format_messages_frame('#FFDD99', 'Warning messages:', $msgs[1])
                . self::format_messages_frame('#DDFF99', 'Information messages:', $msgs[0]);

        if ($cleanup) {
            Module::static_unset_module_variable('Base/EssClient', 'messages');
        }
        return $ret;
    }

    private static function format_messages_frame($bg_color, $title, $messages) {
        $ret = '';
        if (count($messages)) {
            $ret .= '<div class="popup_notice" style="background-color:' . $bg_color . '">';
            $ret .= Base_LangCommon::ts('Base/EssClient', $title);
            foreach ($messages as $m)
                $ret .= '<div class="popup_notice_frame">' . $m . '</div>';
            $ret .= '</div>';
        }
        return $ret;
    }

}

?>