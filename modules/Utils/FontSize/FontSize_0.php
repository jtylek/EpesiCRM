<?php
/**
 * Utils_FontSize class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 * @subpackage font-size
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');


class Utils_FontSize extends Module {
	
	/**
	 * For internal use only.
	 */
	public function body($arg) {
		
		$js = '
			isDigit = function(num) {
				if (num.length>1){ return false; }
				var string = "1234567890";
				if (string.indexOf(num) != -1){ return true; }
				return false;
			}

			Utils_FontSize_changeFontSize = function( delta ) {
				var currentSize = document.body.style.fontSize;
				var newSize = "100";
				if( currentSize ) {
					var size = \'\';
					for( i = 0; i < currentSize.length; i++) {
						if( isDigit(currentSize.charAt(i)) ) 
							size += currentSize.charAt(i)
					}
					if( currentSize.indexOf("%") != -1 ) {
						currentSize = size * 1;
					} else {
						currentSize = 100;
					}
				} else {
					currentSize = 100;
				}
				newSize = (currentSize + delta);
				if(newSize < 10) { newSize = 10; }
				document.body.style.fontSize = newSize + "%";
			}
		';
		eval_js($js);
		$theme =  & $this->pack_module('Base/Theme');
		// onMouseOver="Base_FontSize_overIncrease()" onMouseOut="Base_FontSize_outIncrease()"
		// onMouseOver="Base_FontSize_overDecrease()" onMouseOut="Base_FontSize_outDecrease()"
		$theme->assign('increaseOnClick', 'href=javascript:Utils_FontSize_changeFontSize(10)');
		$theme->assign('decreaseOnClick', 'href=javascript:Utils_FontSize_changeFontSize(-10)');
		$theme->display();
	}
}
?>
