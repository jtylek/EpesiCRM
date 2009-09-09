<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');


class ErrorObserver
{
	public function update_observer($type, $message, $errfile, $errline, $errcontext)
	{
		return true;
	}
}

class ErrorHandler {
	private static $observers = array();
	
	private static function notify_client($buffer) {
		if(JS_OUTPUT && class_exists('Epesi')) {
			chdir(dirname(dirname(__FILE__)));
			Epesi::clean();
			if(DISPLAY_ERRORS)
				Epesi::text($buffer,'error_box','prepend');
			Epesi::alert('There was an error in one of epesi modules.'.((DISPLAY_ERRORS)?' Details are displayed at the bottom of the page, please send this information to system administrator.':''));
			return Epesi::get_output();
		}
		return $buffer;
	}
	
	public static function handle_fatal($buffer) {
	    if (preg_match("/(error<\/b>:)(.+)(<br)/", $buffer, $regs)  || preg_match("/(error:)(.+)(\n)/", $buffer, $regs) ) {
		$err = preg_replace("/<.*?>/","",$regs[2]);
		error_log($err);
		return self::notify_client('Fatal: '.$err);
	    }
	    return $buffer;
	}

	public static function handle_error($type, $message,$errfile,$errline,$errcontext) {
		if (error_reporting()) {

			if ( ! self::notify_observers($type, $message,$errfile,$errline,$errcontext)) {
				return false;
			}

			if(REPORT_ALL_ERRORS)
				$breakLevel = E_ALL;
			else
				$breakLevel = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

			if (($type & $breakLevel) > 0) {
				$backtrace = self::debug_backtrace();
				
				while(@ob_end_clean());
				echo self::notify_client('Type: '.$type.'<br>Message: '.$message.'<br>File: '.$errfile.'<br>Line='.$errline.$backtrace.'<hr>');
				exit();
			}
		}

		return true;
	}

	public static function debug_backtrace() {
		if(function_exists('debug_backtrace')) {
			$backtrace = '';
			$bt = debug_backtrace();
		   
			for($i = 0; $i <= count($bt) - 1; $i++) {
				if(!isset($bt[$i]["file"]))
					$backtrace .= "[PHP core called function]<br />";
				else
					$backtrace .= "File: ".$bt[$i]["file"]."<br />";
	   
						if(isset($bt[$i]["line"]))
					$backtrace .= "&nbsp;&nbsp;&nbsp;&nbsp;line ".$bt[$i]["line"]."<br />";
				$backtrace .= "&nbsp;&nbsp;&nbsp;&nbsp;function called: ".$bt[$i]["function"];
				$backtrace .= "<br /><br />";
			}
			$backtrace = '<br>error backtrace:<br>'.$backtrace;
		} else $backtrace = '';
		return $backtrace;
	}
	

	public static function add_observer(&$observer) {
		if (!is_object($observer))
			return false;

		if ( ! is_subclass_of($observer, 'ErrorObserver'))
			return false;

		if ( ! isset(self::$observers)) {
			self::$observers = array();
		}

		self::$observers[] =& $observer;

		return true;
	}

	private static function notify_observers($type, $message, $errfile, $errline, $errcontext)
	{
		if (empty(self::$observers)) {

			return true;
		}

		$returnValue = true;

		foreach (self::$observers as $observer) {

			$eventValue = $observer->update_observer($type, $message, $errfile, $errline, $errcontext);

			if (is_bool($eventValue)) {

				$returnValue &= $eventValue;
			}
		}

		return $returnValue;
	}
}

//sometimes set_error_handler doesn't work with classes
function handle_epesi_error($type, $message,$errfile,$errline,$errcontext) {
	return ErrorHandler::handle_error($type, htmlspecialchars($message),$errfile,$errline,$errcontext);
}
if(REPORT_ALL_ERRORS)
	error_reporting(E_ALL ^ E_NOTICE); //all without notices
else
	error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
	
set_error_handler('handle_epesi_error');
?>
