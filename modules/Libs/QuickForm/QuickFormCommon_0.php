<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('requires.php');
require_once('Renderer/TCMSArraySmarty.php');
require_once('Renderer/TCMSDefault.php');

$GLOBALS['_HTML_QuickForm_default_renderer'] = new HTML_QuickForm_Renderer_TCMSDefault();
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['multiselect'] = array('modules/Libs/QuickForm/multiselect.php','HTML_QuickForm_multiselect');
$GLOBALS['_HTML_QuickForm_registered_rules']['comparestring'] = array('HTML_QuickForm_Rule_CompareString', 'Rule/CompareString.php');

class Libs_QuickFormCommon extends ModuleCommon {
	private static $on_submit = '';
	
	public static function add_on_submit_action($action) {
		self::$on_submit .=	$action.';';
	}
	
	public static function get_on_submit_actions() {
		return self::$on_submit;
	}
}
?>
