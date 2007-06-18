<?php
/**
 * Mail class.
 * 
 * This class provides mail sending functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once ('class.phpmailer.php');


class Base_MailCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return 'Mail server settings';
	}

	public static function send($to,$subject,$body,$from_addr, $from_name) {
		$mailer = new PHPMailer();
		$mailer->SetLanguage(Base_LangCommon::get_lang_code(), 'modules/Base/Mail/language/');
		if(!isset($from_addr)) $from_addr = Variable::get('mail_from_addr');
		$mailer->From = $from_addr;
		if(!isset($from_name)) $from_addr = Variable::get('mail_from_name');
		$mailer->FromName = $from_name;
		$mailer->Host = Variable::get('mail_host');
		$mailer->Mailer = Variable::get('mail_method');
		$mailer->WordWrap = 75;
		$mailer->Username = Variable::get('mail_user');
		$mailer->Password = Variable::get('mail_password');
		$mailer->SMTPAuth = Variable::get('mail_auth');
		
		if(is_array($to))
			foreach($to as $m)
				$mailer->AddAddress($m);
		else
			$mailer->AddAddress($to);
		$mailer->Subject = $subject;
		$mailer->Body = $body;
		$ret = $mailer->Send();
		if(!$ret) print($mailer->ErrorInfo.'<br>');
		$mailer->ClearAddresses();
		return $ret;
	}
	
}
?>