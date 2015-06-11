<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 2.0
 * @package epesi-notify
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_NotifyInstall extends ModuleInstall {
	const version = '2.0';

	public function install() {
		$ret = true;
		
		DB::Execute('DROP TABLE IF EXISTS base_notify');

        DB::CreateTable('base_notify','
			token C(64) NOTNULL PRIMARY KEY,
			cache X,
			last_refresh I8');
		
		if(!$ret){
			print('Unable to create table base_notify.<br>');
			return false;
		}
		
		Base_ThemeCommon::install_default_theme($this->get_type());

		Utils_CommonDataCommon::new_id('Base_Notify/Timeout', true);
		Utils_CommonDataCommon::new_array('Base_Notify/Timeout', array(-1=>_M('Disable Notification'), 0=>_M('Manually')), true, true);
		Utils_CommonDataCommon::new_array('Base_Notify/Timeout', array(10000=>_M('10 seconds'), 30000=>_M('30 seconds'), 60000=>_M('1 minute')));
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('base_notify');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		Utils_CommonDataCommon::remove('Base_Notify');
		return $ret;
	}

	public function version() {
		return array(self::version);
	}

	public function requires($v) {
		return array(
		array('name'=>'Base/Acl','version'=>0),
		array('name'=>'Base/User','version'=>0),
		array('name'=>'Base/Theme','version'=>0),
		array('name'=>'Libs/QuickForm','version'=>0));
	}

	public static function info() {
		$html="Pops up tray notification in the OS";
		return array(
		'Description'=>$html,
		'Author'=>'<a href="mailto:ghristov@gmx.de">Georgi Hristov</a>',
		'License'=>'MIT');
	}

	public static function simple_setup() {
		return array('package'=>__('EPESI Core'), 'option'=>__('Web Notifications'), 'version'=>self::version);
	}

}

?>