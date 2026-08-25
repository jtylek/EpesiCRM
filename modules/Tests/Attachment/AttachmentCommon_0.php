<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage Attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_AttachmentCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-paperclip'; }

	public static function menu(){
		return array(_M('Tests')=>array('__submenu__'=>1,'__weight__'=>-10, _M('Attachment page')=>array()));
	}
}

?>
