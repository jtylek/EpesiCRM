<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-Filters
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FiltersCommon extends ModuleCommon {
    public static function user_settings() {
	if(self::Instance()->acl_check('manage')) return array('Filters'=>'edit');
	return array();
    }

    public static function body_access() {
        return self::Instance()->acl_check('manage');
    }
}

?>
