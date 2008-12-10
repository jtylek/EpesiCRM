<?php
/**
 * Apps/MailClient and other CRM functions connector
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-mailclient
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MailClientInstall extends ModuleInstall {

	public function install() {
		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/MailClient', 'contact_addon', 'Mails');
		$ret = true;
		$ret &= DB::CreateTable('crm_mailclient_mails','
			id I4 AUTO KEY NOTNULL,
			delivered_on T NOTNULL,
			contact_id I4 NOTNULL,
			deleted I1 DEFAULT 0,
			sticky I1 DEFAULT 0,
			headers X,
			subject C(255),
			body X,
			body_type C(16),
			body_ctype C(64)',
			array('constraints'=>', FOREIGN KEY (contact_id) REFERENCES contact(ID)'));
		$ret &= DB::CreateTable('crm_mailclient_attachments','
			id I4 AUTO KEY NOTNULL,
			mail_id I4 NOTNULL,
			name C(255)',
			array('constraints'=>', FOREIGN KEY (mail_id) REFERENCES crm_mailclient_mails(ID)'));
		return $ret;
	}
	
	public function uninstall() {
		Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/MailClient', 'contact_addon');
		DB::DropTable('crm_mailclient_attachments');
		DB::DropTable('crm_mailclient_mails');
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
					array('name'=>'Base/Lang','version'=>0),
					array('name'=>'Utils/Tooltip','version'=>0),
					array('name'=>'Utils/GenericBrowser','version'=>0),
					array('name'=>'Apps/MailClient','version'=>0),
					array('name'=>'CRM/Contacts','version'=>0)
				);
	}
	
	public static function info() {
		return array(
			'Description'=>'Apps/MailClient and other CRM functions connector',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>