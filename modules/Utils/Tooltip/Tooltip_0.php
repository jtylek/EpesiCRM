<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 0.9
 * @licence SPL 
 * @package epesi-utils 
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tooltip extends Module {
	private static $styles = array();
	
	public function body( $text, $tip, $style ) {
		if(isset($tip)) {
			print $this->show($text, $tip, $style);
		} else {
			print $text;
		}
	}

	public function show( $text, $tip, $style = 'default' ) {
		return $this->open_tag( $tip, $style ).$text.$this->close_tag();
	}

	public function open_tag( $tip, $style = 'default' ) {
		return '<span '.$this->open_tag_attrs($tip, $style).'>';	
	}

	public function close_tag() {
		return '</span>';
	}

	public function open_tag_attrs( $tip, $style = 'default' ) {
		load_js('modules/Utils/Tooltip/js/Tooltip.js');


		if(Utils_Tooltip::$styles[$style] != 1) {
			print "<div id=div_tip_".$style." style='position: absolute; visibility: hidden;'>";
			$theme = $this->init_module('Base/Theme');
			$theme->assign('tip', '<span id="tooltip_text_'.$style.'"></span>');
				$theme->display($style);
			print "</div>";
			Utils_Tooltip::$styles[$style_id] = 1;
		}
		
		return ' onMouseMove="showTip(\''.htmlspecialchars($tip).'\', \''.$style.'\' , event)" onMouseOut="hideTip(\''.$style.'\')"';
	}

}
?>


