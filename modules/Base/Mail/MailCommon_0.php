<?php
/**
 * Mail class.
 * 
 * This class provides mail sending functionality.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once ('modules/Base/Mail/class.smtp.php');
require_once ('modules/Base/Mail/class.pop3.php');
require_once ('modules/Base/Mail/class.phpmailer.php');

class Base_MailCommon extends Base_AdminModuleCommon {
	/**
	 * For internal use only.
	 */
	public static function admin_caption() {
		return array('label'=>__('Mail server settings'), 'section'=>__('Server Configuration'));
	}
	
	/**
	 * For internal use only.
	 */
	public static function admin_access() {
		return !DEMO_MODE;
	}

	public static function admin_access_levels() {
		return !DEMO_MODE?false:null;
	}
	
	public static function send_critical($to,$subject,$body,$from_addr=null, $from_name=null, $html=false) {
		return self::send($to,$subject,$body,$from_addr, $from_name, $html, true);
	}
	
	public static function check_sending_method($critical=false) {
		$msg = false;
		if (Variable::get('mail_method') != 'smtp' && HOSTING_MODE) {
			$msg = __('Mail server configuration error');
		}
		if ($msg) Base_StatusBarCommon::message($msg,'error');
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
	public static function send($to,$subject,$body,$from_addr=null, $from_name=null, $html=false, $critical=false, $inline_images = array(), $attachments = array()) {
		$mailer = self::new_mailer();
		$mail_use_replyto = Variable::get('mail_use_replyto');
		if(!isset($from_name)) $from_name = Variable::get('mail_from_name');
		if(!isset($from_addr)) {
		  $from_addr = Variable::get('mail_from_addr');
		  if($mail_use_replyto && strpos($mail_use_replyto,'@')!==false)
		    $mailer->AddReplyTo($mail_use_replyto, $from_name);
		  $mailer->SetFrom($from_addr, $from_name);
		} else {
		  $mailer->AddReplyTo($from_addr, $from_name);
		  $from_addr = Variable::get('mail_from_addr');
		  $mailer->SetFrom($from_addr, $from_name);
		}
		
		if(Variable::get('mail_method') == 'smtp') {
			$mailer->IsSMTP();
			$h = explode(':', Variable::get('mail_host'));
			if(count($h)>1)
				$mailer->Port = array_pop($h);
			$mailer->Host = implode(':',$h);
			$mailer->Username = Variable::get('mail_user');
			$mailer->Password = Variable::get('mail_password');
			$mailer->SMTPAuth = Variable::get('mail_auth');
			$security = Variable::get('mail_security', false);
			if($security && preg_match('/^(ssl|tls)\_ssc$/',$security,$matches)) {
				$security = $matches[1];
				$mailer->SMTPOptions = array(
						'ssl'=>array(
							'verify_peer' => false,
							'verify_peer_name' => false,
							'allow_self_signed' => true
						)
				);
			}
            $mailer->SMTPSecure = $security;
            $mailer->SMTPAutoTLS = false;
		} elseif (HOSTING_MODE) {
			if (!$critical) return false;
		}
		
		if(is_array($to))
			foreach($to as $m)
				$mailer->AddAddress($m);
		else
			$mailer->AddAddress($to);
		$mailer->Subject = $subject;
		if($html)
		  	$mailer->MsgHTML($body);
		else {
			$mailer->WordWrap = 75;
			$mailer->Body = $body;
		}
		
		foreach($inline_images as $cid=>$a) {
			$mailer->AddEmbeddedImage($a, $cid, basename($a),'base64','image/'.(preg_match('/\.je?pg$/i',$a)?'jpeg':(preg_match('/\.png$/i',$a)?'png':'gif')));
		}

		foreach ($attachments as $file => $filename) {
			$mailer->AddAttachment($file, $filename);
		}
		
		$mailer->CharSet = "utf-8";
		$ret = $mailer->Send();
//		if(!$ret) print($mailer->ErrorInfo.'<br>');
		$mailer->ClearAddresses();
		return $ret;
	}
	
	public static function new_mailer() {
		$mailer = new PHPMailer();
		$mailer->SetLanguage(Base_LangCommon::get_lang_code(), EPESI_LOCAL_DIR . '/modules/Base/Mail/language/');
		return $mailer;
	}
	
}
?>