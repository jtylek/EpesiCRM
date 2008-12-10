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

class CRM_MailClientCommon extends ModuleCommon {
	public static function copy_action($msg) {
		$from = $msg['headers']['from'];
		if(ereg('^[^<]*<(.+)>$',$from,$reqs))
			$from = $reqs[1];
		$c = CRM_ContactsCommon::get_contacts(array('email'=>$from));
		foreach($c as $i)
			DB::Execute('INSERT INTO crm_mailclient_mails(contact_id,subject,headers,body,body_type,body_ctype) VALUES(%d,%s,%s,%s,%s,%s)',array($i['id'],$msg['subject'],''/*$msg['headers']*/,$msg['body'],$msg['type'],$msg['ctype']));
	}

	public static function mail_actions() {
		return array('Move to contact'=>array('func'=>array('CRM_MailClientCommon','copy_action'),'delete'=>1));
	}
}

?>