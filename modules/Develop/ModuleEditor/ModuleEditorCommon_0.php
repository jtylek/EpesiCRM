<?php

/**
 * Epesi developer editor
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-develop
 * @subpackage moduleeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_ModuleEditorCommon extends ModuleCommon {

    public static function menu() {
        if (Acl::i_am_sa())
            return array(_M('Development')=> array('__submenu__' => 1, _M('File manager')=> array()));
        return array();
    }

}

?>