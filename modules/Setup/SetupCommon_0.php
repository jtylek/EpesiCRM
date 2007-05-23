<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class SetupCommon extends Module {
	public static function body_access() {
		return (Variable::get('anonymous_setup') || Acl::check('Administration','Main'));
	}
}
?>
