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
	private $element;

	public function body($tab, $element, $format, $crits=array(), $filters=array()) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$rb = $this->init_module('Utils/RecordBrowser', $tab, $tab.'_picker');

		$this->element = $element;

		print('<div id="leightbox_'.$element.'" class="leightbox">');
		$this->display_module($rb, array($element, $format, $crits, $filters), 'recordpicker');
		print('</div>');

	}

	public function create_open_link($label) {
		return '<a '.$this->create_open_href().'>'.$label.'</a>';
	}

	public function create_open_href() {
		if(!isset($this->element))
			trigger_error('Cannot get open link/href to record picker without packing firstly.',E_USER_ERROR);
		return 'rel="leightbox_'.$this->element.'" class="lbOn" onmousedown="init_all_rpicker_'.$this->element.'();"';
	}
}

?>
