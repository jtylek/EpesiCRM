<?php
/**
 * Roundcube bindings
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license GPL
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Roundcube
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_RoundcubeCommon extends Base_AdminModuleCommon {
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

	public static function get_mailto_link($v) {
        if(isset($_REQUEST['rc_mailto'])) {
            $x = ModuleManager::get_instance('/Base_Box|0');
            $x->push_main('CRM_Roundcube','new_mail',array($_REQUEST['rc_mailto']));
            unset($_REQUEST['rc_mailto']);
        }
        if (!CRM_RoundcubeCommon::use_standard_mailto()) {
            $ret = Utils_RecordBrowserCommon::get_records_count('rc_accounts',array('epesi_user'=>Acl::get_user()));
            if($ret) {
                return '<a '.Module::create_href(array('rc_mailto'=>$v)).'>'.$v.'</a>';
            }
        }
    	return '<a href="mailto:'.$v.'">'.$v.'</a>';
	}

	public static function attachment_getters() {
	        $ret = Utils_RecordBrowserCommon::get_records_count('rc_accounts',array('epesi_user'=>Acl::get_user()));
		if($ret)
			return array(_M('Mail')=>array('func'=>'mail_file','icon'=>Base_ThemeCommon::get_template_file(CRM_Roundcube::module_name(), 'icon.png')));
	}

	public static function mail_file($f,$d,$file_id) {
		$t = time()+3600*24*7;
		$url = Utils_AttachmentCommon::create_remote($file_id, 'mail', $t);
		$x = ModuleManager::get_instance('/Base_Box|0');
		$x->push_main('CRM_Roundcube','new_mail',array('',__('File attachment, expires on: %s',array(Base_RegionalSettingsCommon::time2reg($t))),"<br /><br />".$url));
	}

    public static function cron() {
        return array('cron_cleanup_session'=>60*24);
    }

    public static function cron_cleanup_session() {
        DB::Execute('DELETE FROM rc_session WHERE changed<%T',array(time()-3600*24));
    }

}

if (isset($_GET['rc_mailto'])) {
    Base_BoxCommon::location('CRM_Roundcube','new_mail',array($_GET['rc_mailto']));
}

?>
