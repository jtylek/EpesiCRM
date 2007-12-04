<?php
/**
 * 
 * @author admin@admin.com
 * @copyright admin@admin.com
 * @license SPL
 * @version 0.1
 * @package utils-recordbrowser--recordpicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_RecordPicker extends Module {
	private $lang;

	public function body($tab, $element, $format, $crits=array(), $filters=array()) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$rb = $this->init_module('Utils/RecordBrowser', $tab, $tab.'_picker');

		print('<div id="leightbox_'.$element.'" class="leightbox">');
		$this->display_module($rb, array($element, $format, $crits, $filters), 'recordpicker');
		print('</div>');
		
	}

	public function open_link($element, $label) {
		return '<a rel="leightbox_'.$element.'" class="lbOn" onmousedown="init_all_rpicker_'.$element.'();">'.$label.'</a>';
	}
}

?>