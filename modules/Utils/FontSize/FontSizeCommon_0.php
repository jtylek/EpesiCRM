<?php
/**
 * Utils_FontSize class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 * @subpackage font-size
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_FontSizeCommon extends Module {

	/**
	 * For internal use only.
	 */
	public static function tool_menu() {
		if(Base_AclCommon::i_am_user()) return array('Font size'=>array());
		return array();
	}
}
?>
