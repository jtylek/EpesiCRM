<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class SetupCommon extends Module {
	public static function body_access() {
		return (Variable::get('anonymous_setup') || Acl::check('Administration','Main'));
	}
}
?>
