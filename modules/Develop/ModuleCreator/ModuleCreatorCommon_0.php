<?php

/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.8
 * @license MIT
 * @package epesi-develop
 * @subpackage ModuleCreator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_ModuleCreatorCommon extends ModuleCommon {

    public static function body_access() {
        return Acl::i_am_sa();
    }

    public static function menu() {
        if (Acl::i_am_sa())
            return array(_M('Development')=> array('__submenu__' => 1, _M('Create Module')=> array('action' => 'new')));
        return array();
    }

}

?>
