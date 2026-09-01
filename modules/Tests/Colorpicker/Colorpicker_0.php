<?php
/**
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage colorpicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Colorpicker extends Module {
	// Sizing/positioning styles are inline (theme-agnostic structure the
	// slider's own JS math depends on - colorpicker.js reads/sets these via
	// clientHeight/offsetHeight/style.top) so the widget still works under
	// the legacy theme, which has no CSS file for this module at all;
	// theme_adminltedark/default.css only layers cosmetic polish (rounded
	// corners, shadow) on top for the AdminLTE family.
	public function create_slider($name, $color, $bg_color) {
		print '<div class="tests-colorpicker-slider" style="position:relative;width:20px;">';
		print '<div id="track_'.$name.'" class="tests-colorpicker-track" style="position:relative;height:256px;width:12px;border:1px solid '.$color.';background:'.$bg_color.';cursor:pointer;">';
		print '<div id="handle_'.$name.'" class="tests-colorpicker-handle" style="position:absolute;left:-4px;height:10px;width:20px;border:1px solid '.$color.';background:#fff;cursor:n-resize;"></div>';
		print '</div>';
		print '</div>';
	}
	public function body() {
		TestsCommon::heading(__('Colorpicker'));
		if (Base_ThemeCommon::is_adminlte_family())
			Base_ThemeCommon::load_css(Tests_ColorpickerCommon::module_name(), 'default');

		print '<div class="card"><div class="card-body d-flex align-items-start gap-4">';
		$this->create_slider('red', '#993333', '#e6cfcf');
		$this->create_slider('green', '#339933', '#cfe6cf');
		$this->create_slider('blue', '#336699', '#cfdae6');
		print '<table class="tests-colorpicker-readout">';
			print '<tr><td>R: </td>		<td id="color_red">0</td></tr>';
			print '<tr><td>G: </td>		<td id="color_green">0</td></tr>';
			print '<tr><td>B: </td>		<td id="color_blue">0</td></tr>';
			print '<tr><td>HTML: </td>	<td id="color_html">#000000</td></tr>';
			print '<tr><td>Preview: </td><td><div id="color_preview" class="tests-colorpicker-preview" style="height:17px;width:40px;border:1px dashed #000;background:#000;"></div></td></tr>';
		print '</table>';
		print '</div></div>';

		load_js('modules/Tests/Colorpicker/colorpicker.js');

		TestsCommon::source_card($this, 'modules/Tests/Colorpicker/', array(
			'Install' => 'ColorpickerInstall.php',
			'Main' => 'Colorpicker_0.php',
			'Common' => 'ColorpickerCommon_0.php',
		));
	}

}

?>