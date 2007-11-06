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

class Utils_Tooltip extends Module {
	private static $help_tooltips;
	
	private static function show_help() {
		if(!isset(self::$help_tooltips))
			self::$help_tooltips = Base_User_SettingsCommon::get($this->get_type(),'help_tooltips');
	}
	
	public function construct() {
		self::show_help();
	}

	/**
	 * Displays the tooltip with given text, tip.
	 * Style parameter is optional.
	 * 
	 * @param string text
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 */
	public function body( $text, $tip, $help=true) {
		if(isset($tip)) {
			print $this->create($text, $tip, $help);
		} else {
			print $text;
		}
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
		if(!$help || $this->help_tooltips)
			return $this->open_tag( $tip ).$text.$this->close_tag();
		else
			return $text;
	}

	/**
	 * Returns string that opens HTML tag that will place tooltip over this tag contents.
	 * 
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string HTML tag open
	 */
	public function open_tag( $tip, $help=true ) {
		return '<span '.$this->open_tag_attrs($tip,$help).'>';	
	}

	/**
	 * Returns string that closes HTML tag opened with open_tag() method.
	 * 
	 * @return string HTML tag close
	 */
	public function close_tag() {
		return '</span>';
	}

}
?>


