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
	private static $displayed = false;
	
	/**
	 * Displays the tooltip with given text, tip.
	 * Style parameter is optional.
	 * 
	 * @param string text
	 * @param string tooltip text
	 */
	public function body( $text, $tip) {
		if(isset($tip)) {
			print $this->create($text, $tip);
		} else {
			print $text;
		}
	}

	/**
	 * Returns string that if displayed will create text with tooltip.
	 * 
	 * @param string text
	 * @param string tooltip text
	 * @return string text with tooltip
	 */
	public function create( $text, $tip) {
		return $this->open_tag( $tip ).$text.$this->close_tag();
	}

	/**
	 * Returns string that opens HTML tag that will place tooltip over this tag contents.
	 * 
	 * @param string tooltip text
	 * @return string HTML tag open
	 */
	public function open_tag( $tip ) {
		return '<span '.$this->open_tag_attrs($tip).'>';	
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
	 * 
	 * @param string tooltip text
	 * @return string HTML tag attributes
	 */
	public function open_tag_attrs( $tip ) {
		load_js('modules/Utils/Tooltip/js/Tooltip.js');

		if(!self::$displayed) {
			/*
			print 'XXX<div id="tooltip_div" style="position: absolute; dispay: none; z-index: 2000;">';
			$theme = & $this->init_module('Base/Theme');
			$theme->assign('tip', '<span id="tooltip_text"></span>');
				$theme->display();
			print '</div>';*/
			ob_start();
			$theme = & $this->init_module('Base/Theme');
			$theme->assign('tip', '<span id="tooltip_text"></span>');
				$theme->display();
			$html = ob_get_clean();
			$js = "
				div = document.createElement('div', 'id=tooltip_div');
				div.id = 'tooltip_div';
				div.style.position = 'absolute';
				div.style.display = 'none';
				body = document.getElementsByTagName('body');
				body = body[0];
				document.body.appendChild(div);
				div.innerHTML = '".$html."';
			";
			eval_js($js);
			//<div id="tooltip_div" style="position: absolute; dispay: none; z-index: 2000;"><span id="tooltip_text"></span></div>
			self::$displayed = true;
		}
		
		return ' onMouseMove="Utils_Toltip__showTip(\''.escapeJS(htmlspecialchars($tip)).'\', event)" onMouseOut="Utils_Toltip__hideTip()"';
	}

}
?>


