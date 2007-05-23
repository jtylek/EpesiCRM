<?php
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

	public function show( $text, $tip, $style ) {
		return $this->open_tag( $tip, $style ).$text.$this->close_tag();
	}

	public function open_tag( $tip, $style ) {
		return '<span '.$this->open_tag_attrs($tip, $style).'>';	
	}

	public function close_tag() {
		return '</span>';
	}

	public function open_tag_attrs( $tip, $style=null ) {
		load_js('modules/Utils/Tooltip/js/Tooltip.js');

		if(!isset($style))
			$style_id='__default__';

		if(!isset($this->styles[$style])) {
			$style_id=$style;
			print "<div id=div_tip_".$style_id." style='position: absolute; visibility: hidden;'>";
			$theme = $this->pack_module('Base/Theme');
			$theme->assign('tip', '<span id="tooltip_text_'.$style_id.'"></span>');
			if(!isset($style)) {
				$theme->display();
			} else {
				$theme->display($style);
			}
			print "</div>";
		}
		
		return ' onMouseMove="showTip(\''.htmlspecialchars($tip).'\', \''.$style_id.'\' , event)" onMouseOut="hideTip(\''.$style_id.'\')"';
	}

}
?>


