<?php
/**
 * Mail class.
 * 
 * This class provides mail sending functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once ('modules/Base/Mail/class.smtp.php');
require_once ('modules/Base/Mail/class.pop3.php');
require_once ('modules/Base/Mail/class.phpmailer.php');

class Base_MailCommon extends Base_AdminModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-envelope-at'; }

	private static $last_error = null;

	/**
	 * Message from the warning/notice (if any) raised by the most recent
	 * send() call - see the set_error_handler() note there for why this
	 * exists instead of just checking send()'s own return value.
	 */
	public static function get_last_error() {
		return self::$last_error;
	}

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
	 * @param int|null $timeout SMTP connect timeout override, in seconds - null
	 *   keeps PHPMailer's own default (class.phpmailer.php's $Timeout, 300s).
	 *   Real mail (cron, app-triggered notifications) wants that generous
	 *   default since a slow-but-working server shouldn't drop a send; the
	 *   admin "Test" button (Base_Mail::test_mail_config()) wants the
	 *   opposite - fail fast with a clear error instead of leaving the UI on
	 *   "Loading..." for up to 5 minutes when a host/port is unreachable.
	 * @return true on success, false otherwise
	 */
	public static function send($to,$subject,$body,$from_addr=null, $from_name=null, $html=false, $critical=false, $inline_images = array(), $attachments = array(), $timeout = null) {
		self::$last_error = null;
		$mailer = self::new_mailer();
		if ($timeout !== null) $mailer->Timeout = $timeout;
		$mail_use_replyto = Variable::get('mail_use_replyto');
		if(!isset($from_name)) $from_name = Variable::get('mail_from_name');
		if(!isset($from_addr)) {
		  $from_addr = Variable::get('mail_from_addr');
		  if($mail_use_replyto && str_contains($mail_use_replyto,'@'))
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
		// A failed connection (bad host, TLS cert mismatch, refused auth, ...)
		// is routine here - it's exactly what the admin "Test" button exists
		// to surface - not an application bug, so it shouldn't hit
		// include/error.php's REPORT_ALL_ERRORS handling: that treats any
		// warning outside a compiled Smarty template as fatal (blanks the
		// response and exit()s), which previously fired before this
		// function's own caller ever got a chance to show
		// "An error has occured" - the warning killed the request first.
		// Handling it locally here, then restoring the real handler
		// immediately after, keeps that global safety net intact for actual
		// code bugs elsewhere while letting this expected failure mode
		// return normally so callers (e.g. Base_Mail::test_mail_config())
		// can report it. Still logged same as before, just not fatal.
		set_error_handler(function($errno, $errstr, $errfile, $errline) {
			self::$last_error = $errstr;
			epesi_log("Type: ".ErrorHandler::error_code_to_string($errno)." ($errno)\nMessage: $errstr\nFile: $errfile\nLine=$errline\n\n", 'php_errors.log');
			return true;
		});
		try {
			$ret = $mailer->Send();
		} finally {
			restore_error_handler();
		}
		if (!$ret && !self::$last_error && $mailer->ErrorInfo) self::$last_error = $mailer->ErrorInfo;
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