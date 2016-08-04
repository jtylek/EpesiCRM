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
    public static function get_href($group, $params=array()) {
        $ret = 'href="javascript:void(0)" class="lbOn" rel="'.$group.'_prompt_leightbox"';
        if (!empty($params)) $ret .= ' onmousedown="Utils_LeightboxPrompt.set_params(\''.$group.'\', \'' . http_build_query($params) . '\');"';
        return $ret;
    }

    public static function open($group, $params=array()) {
    	eval_js(self::get_open_js($group, $params));
    }
    
    public static function get_open_js($group, $params=array()) {
    	return 'Utils_LeightboxPrompt.activate(\''.$group.'\', \'' . http_build_query($params). '\');';
    }
}

?>
