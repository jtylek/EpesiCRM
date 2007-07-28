<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-shoutbox
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ShoutboxInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Base/Acl','version'=>0),
			array('name'=>'Base/User','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>