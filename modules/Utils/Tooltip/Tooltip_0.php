<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9
 * @licence SPL 
 * @package epesi-utils 
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tooltip extends Module {
	private static $styles = array();
	
	/**
	 * Displays the tooltip with given text, tip and style.
	 * Style parameter is optional.
	 * 
	 * @param string text
	 * @param string tooltip text
	 * @param string style
	 */
	public function body( $text, $tip, $style ) {
		if(isset($tip)) {
			print $this->create($text, $tip, $style);
		} else {
			print $text;
		}
	}

	/**
	 * Returns string that if displayed will create text with tooltip.
	 * Style parameter is optional.
	 * 
	 * @param string text
	 * @param string tooltip text
	 * @param string style
	 * @return string text with tooltip
	 */
	public function create( $text, $tip, $style = 'default' ) {
		return $this->open_tag( $tip, $style ).$text.$this->close_tag();
	}

	/**
	 * Returns string that opens HTML tag that will place tooltip over this tag contents.
	 * Style parameter is optional.
	 * 
	 * @param string tooltip text
	 * @param string style
	 * @return string HTML tag open
	 */
	public function open_tag( $tip, $style = 'default' ) {
		return '<span '.$this->open_tag_attrs($tip, $style).'>';	
	}

	/**
	 * Returns string that closes HTML tag opened with open_tag() method.
	 * 
	 * @return string HTML tag close
	 */
	public function close_tag() {
		return '</span>';
	}

	/**
	 * Returns string that when placed as tag attribute 
	 * will enable tooltip when placing mouse over that element.
	 * Style parameter is optional.
	 * 
	 * @param string tooltip text
	 * @param string style
	 * @return string HTML tag attributes
	 */
	public function open_tag_attrs( $tip, $style = 'default' ) {
		load_js('modules/Utils/Tooltip/js/Tooltip.js');

		if(Utils_Tooltip::$styles[$style] != 1) {
			print "<div id=div_tip_".$style." style='position: absolute; visibility: hidden;'>";
			$theme = & $this->init_module('Base/Theme');
			$theme->assign('tip', '<span id="tooltip_text_'.$style.'"></span>');
				$theme->display($style);
			print "</div>";
			Utils_Tooltip::$styles[$style] = 1;
		}
		
		return ' onMouseMove="showTip(\''.htmlspecialchars($tip).'\', \''.$style.'\' , event)" onMouseOut="hideTip(\''.$style.'\')"';
	}

}
?>


