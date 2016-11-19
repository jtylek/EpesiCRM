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
	public function update_observer($type, $message, $errfile, $errline, $errcontext, $backtrace)
	{
		return true;
	}
}

class ErrorHandler {
	private static $observers = array();
	
	private static function notify_client($buffer) {
        epesi_log("$buffer\n\n",'php_errors.log');

		if(JS_OUTPUT && class_exists('Epesi')) {
			chdir(dirname(dirname(__FILE__)));
			Epesi::clean();
			if(DISPLAY_ERRORS) {
				Epesi::js("$('debug_content').style.display='block';");
				Epesi::text('<pre>' . $buffer . '</pre><hr>','error_box','prepend');
			}
			Epesi::alert('There was an error in one of epesi modules.'.((DISPLAY_ERRORS)?' Details are displayed at the bottom of the page, please send this information to system administrator.':''));
			return Epesi::get_output();
		}
		return $buffer;
	}
	
	public static function handle_error($type, $message,$errfile,$errline,$errcontext) {
    	if (($type & error_reporting()) > 0) {
				$backtrace = self::debug_backtrace();

                $message = "Type: " . self::error_code_to_string($type) . " ($type)\nMessage: $message\nFile: $errfile\nLine={$errline}{$backtrace}";

				if ( ! self::notify_observers($type, $message,$errfile,$errline,$errcontext,$backtrace)) {
					return false;
				}

				while(@ob_end_clean());
				echo self::notify_client($message);
				exit();
		}

		return true;
	}

    public static function handle_exception($exception)
    {
        $backtrace = self::debug_backtrace($exception->getTrace());
        while (@ob_end_clean());
        $msg = get_class($exception) . "\nMessage: "
               . $exception->getMessage() . "\nFile: " .
               $exception->getFile() . "\nLine=" . $exception->getLine() .
               $backtrace . "\n\n";
        echo self::notify_client($msg);
    }
    
    public static function error_code_to_string($type) {
        $constants = get_defined_constants(true);
        // based on changelog of get_defined_constants
        $key = 'Core';
        if (version_compare(phpversion(), "5.3.0", "<"))
            $key = 'internal';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
                && version_compare(phpversion(), "5.3.1", "<")
                && version_compare(phpversion(), "5.3.0", ">="))
            $key = 'mhash';
        $clist = & $constants[$key];
        if (is_array($clist))
            foreach ($clist as $constant => $value) {
                if ($value == $type && $constant[0] == 'E')
                    return $constant;
            }
        return 'error type not found';
    }

	public static function debug_backtrace($bt = null) {
		if($bt === null && function_exists('debug_backtrace')) {
            $bt = debug_backtrace();
        }
        if ($bt !== null) {
			$backtrace = "\nerror backtrace:\n";

			for($i = 0; $i <= count($bt) - 1; $i++) {
				if(isset($bt[$i]['file']) && ($bt[$i]['function']=='debug_backtrace' || $bt[$i]['function']=='handle_error') && preg_match('/error.php$/',$bt[$i]["file"]))
					continue;
				if(!isset($bt[$i]["file"]))
					$backtrace .= "[PHP core called function]\n";
				else
					$backtrace .= "File: ".$bt[$i]["file"]."\n";
	   
						if(isset($bt[$i]["line"]))
					$backtrace .= "    line ".$bt[$i]["line"]."\n";
				$backtrace .= "    function called: ".$bt[$i]["function"];
				if(isset($bt[$i]['args'])) {
				    $args = $bt[$i]['args'];
				    foreach($args as & $arg) {
				        if(is_string($arg)) {
				            $arg = '"'.addcslashes($arg,'"').'"';
				            continue;
				        }
				        if(is_numeric($arg)) continue;
	                    if(is_null($arg)) {
	                        $arg = 'null';
	                        continue;
	                    }
				        if(is_bool($arg)) {
				            $arg = $arg?'true':'false';
				            continue;
				        }
				        if(is_object($arg)) {
				            $arg = 'Object ('.get_class($arg).')';
				            continue;
				        }
				        if(is_array($arg)) {
				            if(count($arg)>10) $arg = 'Array (#'.count($arg).')';
				            else {
				                foreach($arg as &$a) {
				                    if(is_string($a)) {
            				            $a = '"'.addcslashes($a,'"').'"';
				                        continue;
				                    }
				                    if(is_numeric($a)) continue;
				                    if(is_null($a)) {
				                        $a = 'null';
				                        continue;
				                    }
            				        if(is_bool($a)) {
			            	            $a = $a?'true':'false';
				                        continue;
            				        }
				                    if(is_object($a)) {
				                        $a = 'Object ('.get_class($a).')';
				                        continue;
				                    }
				                    if(is_array($a)) {
				                        $a = 'Array (#'.count($a).')';
				                        continue;
				                    }
				                    $a = '???';
				                }
				                $arg = 'Array ('.implode(', ',$arg).')';
				            }
				            continue;
				        }
				        $arg = '???';
				    }
				    $backtrace .= '('.implode(', ',$args).')';
				}
				$backtrace .= "\n\n";
			}
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

	private static function notify_observers($type, $message, $errfile, $errline, $errcontext, $backtrace)
	{
		if (empty(self::$observers)) {

			return true;
		}

		$returnValue = true;

		foreach (self::$observers as $observer) {

			$eventValue = $observer->update_observer($type, $message, $errfile, $errline, $errcontext, $backtrace);

			if (is_bool($eventValue)) {

				$returnValue &= $eventValue;
			}
		}

		return $returnValue;
	}
}

//sometimes set_error_handler doesn't work with classes
function handle_epesi_error($type, $message, $errfile, $errline, $errcontext)
{
    if (($type & error_reporting()) > 0) {
        if (class_exists('ErrorHandler')) {
            return ErrorHandler::handle_error($type, $message, $errfile, $errline, $errcontext);
        }

        echo 'Error (' . $type . '): ' . $message . ' in ' . $errfile . ':' . $errline;
        exit();
    }
    return true;
}

function handle_epesi_exception($exception)
{
    if (class_exists('ErrorHandler')) {
        ErrorHandler::handle_exception($exception);
    } else {
        $msg = get_class($exception) . ': ' . $exception->getMessage()
               . '<br/>' . $exception->getTraceAsString();
        echo $msg;
    }
}

function check_for_fatal()
{
    global $error_reporting_level;
    $error = error_get_last();
    $fatal_reporting_level = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
    if ( $error["type"] & $error_reporting_level & $fatal_reporting_level )
        ErrorHandler::handle_error( $error["type"], $error["message"], $error["file"], $error["line"], '' );
}

function epesi_log($message,$file) {
    $logs_dir = EPESI_LOCAL_DIR . '/' . DATA_DIR . '/logs';
    if(!file_exists($logs_dir)) {
        @mkdir($logs_dir);
        file_put_contents($logs_dir . '/.htaccess','deny from all');
        file_put_contents($logs_dir . '/index.html','');
    }
    error_log(date('Y-m-d H:i:s').' '.$message,3,$logs_dir . '/' . $file);
}

if(REPORT_ALL_ERRORS) {
    if (version_compare(phpversion(), '5.4.0')==-1)
    	$error_reporting_level = E_ALL; //all without notices
    else
        $error_reporting_level = E_ALL & ~E_STRICT & ~E_DEPRECATED; // E_STRICT cause 5.4 unusable, E_DEPRECATED
}
else
	$error_reporting_level = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

error_reporting($error_reporting_level);
register_shutdown_function( "check_for_fatal" );
set_error_handler('handle_epesi_error', $error_reporting_level);
set_exception_handler('handle_epesi_exception');
