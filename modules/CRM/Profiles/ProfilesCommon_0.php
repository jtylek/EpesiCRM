<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-profiles
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProfilesCommon extends ModuleCommon {
    public static function user_settings() {
	if(self::Instance()->acl_check('manage')) return array('Profiles'=>'edit');
	return array();
    }

    public static function body_access() {
        return self::Instance()->acl_check('manage');
    }
}

?>
