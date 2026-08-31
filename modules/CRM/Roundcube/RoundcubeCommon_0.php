<?php
/**
 * Roundcube bindings
 * @author pbukowski@telaxus.com
 * @copyright Janusz Tylek
 * @license GPL
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Roundcube
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_RoundcubeCommon extends Base_AdminModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-envelope-open-fill'; }

    public static function menu() {
		if (Utils_RecordBrowserCommon::get_access('rc_accounts', 'browse'))
			return array(_M('E-mail')=>array());
        return array();
    }

    public static function user_settings() {
        if(Utils_RecordBrowserCommon::get_access('rc_accounts', 'browse')) {
            return array(__('Roundcube settings')=>array(
                array('name'=>'standard_mailto','label'=>__("Use standard mailto links"),'type'=>'checkbox','default'=>0)
            ));
        }
        return array();
    }

    public static function use_standard_mailto() {
        return Base_User_SettingsCommon::get('CRM_Roundcube', 'standard_mailto');
    }

    public static function set_standard_mailto($value)
    {
        Base_User_SettingsCommon::save('CRM_Roundcube', 'standard_mailto', $value);
    }

    /**
     * Does the current user have any mail account configured?
     *
     * Request-scoped memo, keyed by user id. get_mailto_link() below is a grid column
     * formatter - it runs once per visible e-mail cell, and the query it used to issue
     * directly takes no per-row argument at all, so a 20-row Contacts: Browse spent 20
     * identical build_query()+COUNT(*) round trips answering the same question. Keyed by
     * user rather than cached as a bare bool because Acl::get_user() genuinely changes
     * mid-request on some paths (e.g. Utils/RecordBrowser/indexer.php's set_sa_user()).
     *
     * Request-scoped only, same discipline as the other grid caches - see
     * AI-shared/performance-profiling.md.
     */
    private static function user_has_mail_account() {
        static $cache = array();
        $uid = Acl::get_user();
        if (!isset($cache[$uid])) {
            $cache[$uid] = (bool) Utils_RecordBrowserCommon::get_records_count('rc_accounts', array('epesi_user' => $uid));
        }
        return $cache[$uid];
    }

	public static function get_mailto_link($v) {
        if(isset($_REQUEST['rc_mailto'])) {
            Base_BoxCommon::push_module(CRM_Roundcube::module_name(),'new_mail',array($_REQUEST['rc_mailto']));
            unset($_REQUEST['rc_mailto']);
        }
        if (!CRM_RoundcubeCommon::use_standard_mailto()) {
            if(self::user_has_mail_account()) {
                return '<a '.Module::create_href(array('rc_mailto'=>$v)).'>'.$v.'</a>';
            }
        }
    	return '<a href="mailto:'.$v.'">'.$v.'</a>';
	}

	public static function attachment_getters() {
		if(self::user_has_mail_account())
			return array(_M('Mail')=>array('func'=>'mail_file','icon'=>Base_ThemeCommon::get_template_file(CRM_Roundcube::module_name(), 'icon.png')));
	}

    public static function file_field_getters() {
        if(self::user_has_mail_account())
            // 'icon' (a PNG) is the legacy-theme fallback; 'bi' is a Bootstrap
            // icon class name, used by Utils_FileStorage's AdminLTE-dark
            // download.tpl instead of the PNG where supported.
            return array(_M('Mail')=>array('func'=>'mail_file_field','icon'=>Base_ThemeCommon::get_template_file(CRM_Roundcube::module_name(), 'icon.png'),'bi'=>'bi-envelope'));
    }

    public static function mail_file_field($backref) {
        $url = CRM_Roundcube_RemoteAttachment::getInstance()->callCreateRemote($backref);
        Base_BoxCommon::push_module(CRM_Roundcube::module_name(),'new_mail',array('',__('File attachment, expires on: %s',array(Base_RegionalSettingsCommon::time2reg('+7 days'))),"<br /><br />".$url));
    }

	public static function mail_file($f,$d,$file_id) {
		$t = time()+3600*24*7;
		$url = Utils_AttachmentCommon::create_remote($file_id, 'mail', $t);
		Base_BoxCommon::push_module(CRM_Roundcube::module_name(),'new_mail',array('',__('File attachment, expires on: %s',array(Base_RegionalSettingsCommon::time2reg($t))),"<br /><br />".$url));
	}

    public static function cron() {
        return array('cron_cleanup_session'=>60*24);
    }

    public static function cron_cleanup_session() {
        DB::Execute('DELETE FROM rc_session WHERE expires_at<%T',array(time()-3600*24));
    }

    public static function multiwin_supported()
    {
        $supported = Cache::get('rc_multiwin');
        if ($supported === null) {
            $test_url = get_epesi_url() . '/modules/Libs/RoundCube/RCWIN_0/robots.txt';
            $ret = '';
            // This is a same-server loopback request purely to check whether the RCWIN_
            // rewrite is in effect, not a security-sensitive external connection (same
            // trust call already made for the real IMAP/SMTP connections' verify_peer
            // in config.inc.php) - skip TLS verification, or a self-signed/otherwise-
            // untrusted cert on an https:// install (e.g. local dev) makes this request
            // fail outright and permanently misreports multiwin as unsupported.
            if(ini_get('allow_url_fopen')) {
                $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
                $ret = @file_get_contents($test_url, false, $ctx);
            } elseif (extension_loaded('curl')) { // Test if curl is loaded
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
                curl_setopt($ch, CURLOPT_URL, $test_url);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $ret = curl_exec($ch);
                curl_close($ch);
            }
            $supported = strpos($ret, 'User-agent') !== false;
            // A negative result can be transient (mod_rewrite/.htaccess not yet in
            // effect right after deploy, a momentary network hiccup on the self-request)
            // rather than a permanent hosting limitation, so don't cache "false" forever
            // or it can never self-heal - retest hourly. A positive result is stable.
            Cache::set('rc_multiwin', $supported, $supported ? 86400 : 3600);
        }
        return $supported;
    }

}

if (isset($_GET['rc_mailto'])) {
    Base_BoxCommon::location('CRM_Roundcube','new_mail',array($_GET['rc_mailto']));
}

?>
