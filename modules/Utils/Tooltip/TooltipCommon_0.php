<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9
 * @license SPL 
 * @package epesi-utils 
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TooltipCommon extends ModuleCommon {
	public static function user_settings(){
		return array('Misc'=>array(
			array('name'=>'help_tooltips','label'=>'Show help tooltips','type'=>'checkbox','default'=>1)
			));
	}
}
?>