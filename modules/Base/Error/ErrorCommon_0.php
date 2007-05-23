<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class EpesiErrorObserver extends ErrorObserver {
	public function update_observer($type, $message,$errfile,$errline,$errcontext) {
		if($type & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR | E_COMPILE_ERROR)) {
			if(function_exists('debug_print_backtrace')) { 
				debug_print_backtrace();
				$backtrace = "\nerror backtrace:\n".ob_get_contents();
			} else $backtrace = '';
			Base_MailCommon::send('bugs@telaxus.com','Epesi Error',"who=".Acl::get_user()."\ntype=".$type."\nmessage=".$message."\nerror file=".$errfile."\nerror line=".$errline."\nerror context=".var_export($errcontext,true).$backtrace);
		}
		return true;
	}
}

$err = new EpesiErrorObserver();
ErrorHandler::add_observer($err);

?>
