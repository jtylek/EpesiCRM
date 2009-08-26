<?php
/**
 * Mail class.
 * 
 * This class provides mail sending functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once ('modules/Base/Mail/class.phpmailer.php');

class Base_MailCommon extends Base_AdminModuleCommon {
	/**
	 * For internal use only.
	 */
	public static function admin_caption() {
		return 'Mail server settings';
	}
	
	/**
	 * For internal use only.
	 */
	public static function admin_access() {
		return Base_AclCommon::i_am_sa();
	}

	/**
	 * Sends an email.
	 * 
	 * Server settings are stored in epesi variables
	 * and can be changed by administrator.
	 * 
	 * @param string recipent
	 * @param string subject
	 * @param string email message
	 * @param string sender
	 * @param string sender's name
	 * @return true on success, false otherwise
	 */
	public static function send($to,$subject,$body,$from_addr=null, $from_name=null) {
		$mailer = self::new_mailer();
		if(!isset($from_addr)) $from_addr = Variable::get('mail_from_addr');
		if(!isset($from_name)) $from_name = Variable::get('mail_from_name');
		$mailer->SetFrom($from_addr, $from_name);
		if(Variable::get('mail_method') == 'smtp') {
			$mailer->IsSMTP();
			$h = explode(':', Variable::get('mail_host'));
			$mailer->Host = $h[0];
			if(isset($h[1]))
				$mailer->Port = $h[1];
			$mailer->Username = Variable::get('mail_user');
			$mailer->Password = Variable::get('mail_password');
			$mailer->SMTPAuth = Variable::get('mail_auth');
		}
		$mailer->WordWrap = 75;
		
		if(is_array($to))
			foreach($to as $m)
				$mailer->AddAddress($m);
		else
			$mailer->AddAddress($to);
		$mailer->Subject = $subject;
		$mailer->Body = $body;
		$mailer->CharSet = "utf-8";
		$ret = $mailer->Send();
		if(!$ret) print($mailer->ErrorInfo.'<br>');
		$mailer->ClearAddresses();
		return $ret;
	}
	
	public static function new_mailer() {
		$mailer = new PHPMailer();
		$mailer->SetLanguage(Base_LangCommon::get_lang_code(), 'modules/Base/Mail/language/');
		return $mailer;
	}
	
}
?>