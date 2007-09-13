<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package apps-activeboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActiveBoardCommon extends ModuleCommon {
	public static function tool_menu() {
		return array('Active board'=>array());
	}
}

?>