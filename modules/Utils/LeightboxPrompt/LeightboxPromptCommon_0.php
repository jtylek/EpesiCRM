<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage LeightboxPrompt
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_LeightboxPromptCommon extends ModuleCommon {
    public function get_href($group, $params=array()) {
        $ret = 'href="javascript:void(0)" class="lbOn" rel="'.$group.'_followups_leightbox"';
        if (!empty($params)) $ret .= ' onmousedown="f'.$group.'_set_params(\''.implode('\',\'',$params).'\');"';
        return $ret;
    }
}

?>
