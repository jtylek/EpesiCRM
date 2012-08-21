<?php
/**
 * Provides error to mail handling.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage error
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class EpesiErrorObserver extends ErrorObserver {
	public function update_observer($type, $message,$errfile,$errline,$errcontext, $backtrace) {
		$mail = Variable::get('error_mail');
		if($mail) {
			$backtrace = htmlspecialchars_decode(str_replace(array('<br />','&nbsp;'),array("\n",' '),$backtrace));
			$x = "who=".Base_AclCommon::get_user()."\ntype=".$type."\nmessage=".$message."\nerror file=".$errfile."\nerror line=".$errline."\n".$backtrace;
			$d = ModuleManager::get_data_dir('Base/Error').md5($x).'.txt';
			file_put_contents($d,$x);
			$url = get_epesi_url();
			Base_MailCommon::send($mail,'Epesi Error - '.$url,substr($x,0,strpos($x,"error backtrace"))."\n".$url.'/'.$d);
		}
		return true;
	}
}

$err = new EpesiErrorObserver();
ErrorHandler::add_observer($err);

class Base_ErrorCommon extends ModuleCommon implements Base_AdminModuleCommonInterface {
	public static function admin_caption() {
		return array('label'=>__('PHP & SQL Errors to mail'), 'section'=>__('Server Configuration'));
	}

	public static function admin_access() {
		return !DEMO_MODE;
	}

	public static function admin_access_levels() {
		return !DEMO_MODE?false:null;
	}

}


?>
