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
	if(Base_AclCommon::i_am_user()) return array('Account'=>'body');
	return array();
    }

}

?>
