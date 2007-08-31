<?php
/**
 * Provides error to mail handling.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage error
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class EpesiErrorObserver extends ErrorObserver {
	public function update_observer($type, $message,$errfile,$errline,$errcontext) {
		if($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR | E_COMPILE_ERROR)) {
			if(function_exists('debug_print_backtrace')) { 
				debug_print_backtrace();
				$backtrace = "\nerror backtrace:\n".ob_get_contents();
			} else $backtrace = '';
			$x = "who=".Acl::get_user()."\ntype=".$type."\nmessage=".$message."\nerror file=".$errfile."\nerror line=".$errline."\n".$backtrace;
			$d = $this->get_data_dir().md5($x).'.txt';
			file_put_contents($d,$x);
			$url = 'http'.(isset($_SERVER['HTTPS'])?'s':'').'://'. $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']);
			Base_MailCommon::send('pbukowski@telaxus.com','Epesi Error',$url.'/'.$d);
		}
		return true;
	}
}

$err = new EpesiErrorObserver();
ErrorHandler::add_observer($err);

?>
