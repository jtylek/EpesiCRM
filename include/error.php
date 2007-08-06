<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
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
		global $base;
		if(class_exists('Saja')) {
			if(!isset($base)) $base = new Saja();
			$base->text($buffer,'error_box,p');
			$base->alert('There was an error in one of epesi modules. Details are displayed at the bottom of the page, please send this information to system administrator.');
			return $base->send();	
		}
		return $buffer;
	}
	
	public static function handle_fatal($buffer) {
	    if (ereg("(error</b>:)(.+)(<br)", $buffer, $regs) ) {
		$err = preg_replace("/<.*?>/","",$regs[2]);
		error_log($err);
		return self::notify_client($err);
	    }
	    return $buffer;
	}

	public static function handle_error($type, $message,$errfile,$errline,$errcontext) {
		if (error_reporting()) {

			if ( ! self::notify_observers($type, $message,$errfile,$errline,$errcontext)) {
				return false;
			}

			$breakLevel = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

			if (($type & $breakLevel) > 0) {
				if(function_exists('debug_backtrace')) {
					ob_start();
					$bt = debug_backtrace();
   				   
 					for($i = 0; $i <= count($bt) - 1; $i++) {
 						if(!isset($bt[$i]["file"]))
							echo("[PHP core called function]<br />");
						else
							echo("File: ".$bt[$i]["file"]."<br />");
       
						if(isset($bt[$i]["line"]))
							echo("&nbsp;&nbsp;&nbsp;&nbsp;line ".$bt[$i]["line"]."<br />");
						echo("&nbsp;&nbsp;&nbsp;&nbsp;function called: ".$bt[$i]["function"]);
						echo("<br /><br />");
					}
					$backtrace = ob_get_contents();
					ob_end_clean(); 
					$backtrace = '<br>error backtrace:<br>'.$backtrace;
				} else $backtrace = '';

				echo self::notify_client('type='.$type.'<br>message='.$message.'<br>error file='.$errfile.'<br>error line='.$errline.'<br>error context='.print_r($errcontext,true).$backtrace.'<hr>');
				exit();
			}
		}

		return true;
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

set_error_handler(array('ErrorHandler', 'handle_error'));
ini_set('display_errors','off');
?>
