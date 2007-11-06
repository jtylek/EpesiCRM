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

	private static $help_tooltips;
	private static function show_help() {
		if(!isset(self::$help_tooltips))
			self::$help_tooltips = Base_User_SettingsCommon::get('Utils/Tooltip','help_tooltips');
	}
	

	/**
	 * Returns string that when placed as tag attribute 
	 * will enable tooltip when placing mouse over that element.
	 * 
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string HTML tag attributes
	 */
	public static function open_tag_attrs( $tip, $help=true ) {
		self::show_help();
		if($help && !self::$help_tooltips) return '';
		load_js('modules/Utils/Tooltip/js/Tooltip.js');
		if(!isset($_SESSION['client']['utils_tooltip'])) {
			load_css(Base_ThemeCommon::get_template_filename('Utils_Tooltip','default.css'));
			$js = 'div = document.createElement(\'div\');'.
				'div.id = \'tooltip_div\';'.
				'div.style.position = \'absolute\';'.
				'div.style.display = \'none\';'.
				'div.style.zIndex = 2000;'.
				'div.style.left = 0;'.
				'div.style.top = 0;'.
				'div.onmouseover = Utils_Toltip__hideTip;'.
				'div.innerHTML = \'<span id="tooltip_text"></span>\';'.
				'body = document.getElementsByTagName(\'body\');'.
				'body = body[0];'.
				'document.body.appendChild(div);';
			eval_js($js);
			$_SESSION['client']['utils_tooltip'] = true;
		}
		return ' onMouseMove="Utils_Toltip__showTip(\''.escapeJS(htmlspecialchars($tip)).'\', event)" onMouseOut="Utils_Toltip__hideTip()" onMouseUp="Utils_Toltip__hideTip()" ';
	}

	/**
	 * Returns string that if displayed will create text with tooltip.
	 * 
	 * @param string text
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string text with tooltip
	 */
	public function create( $text, $tip, $help=true) {
		self::show_help();
		if(!$help || self::$help_tooltips)
			return '<span '.self::open_tag_attrs($tip,$help).'>'.$text.'</span>';
		else
			return $text;
	}

}
?>