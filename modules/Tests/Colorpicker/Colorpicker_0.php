<?php
/**
 * @author Kuba Sławiński
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage colorpicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Colorpicker extends Module {
//red 
//color: #993333
//background-color: #e6cfcf

//green 
//color: #339933
//background-color: #cfe6cf

//blue
//color: #336699
//background-color: #cfdae6
	public function create_slider($name, $color, $bg_color) {
		print '<div id=track_'.$name.' style="height: 256px; width: 12px; border: 1px solid '.$color.'; background: '.$bg_color.'">';
		print '<div id=handle_'.$name.' style="height: 4px; width: 10px; border: 1px solid '.$color.'; cursor: n-resize;"></div>';
		print '</div>';
	}
	public function body() {
		print '<table><tr><td style="width: 20px">';
		$this->create_slider('red', '#993333', '#e6cfcf');
		print '</td><td style="width: 20px">';
		$this->create_slider('green', '#339933', '#cfe6cf');
		print '</td><td style="width: 20px">';
		$this->create_slider('blue', '#336699', '#cfdae6');
		print '</td><td style="width: 300px">';
			print '<table>';
				print '<tr><td>R: </td>		<td id=color_red>0</td></tr>';
				print '<tr><td>G: </td>		<td id=color_green>0</td></tr>';
				print '<tr><td>B: </td>		<td id=color_blue>0</td></tr>';
				print '<tr><td>HTML: </td>	<td id=color_html>#000000</td></tr>';
				print '<tr><td>Preview: </td><td><div id=color_preview style="height: 17px; width: 40px; border: 1px dashed black; background: black"></div></td></tr>';
				
			print '</table>';
		print '</td></tr></table>';
		
		load_js('modules/Tests/Colorpicker/colorpicker.js');
	}

}

?>