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

	public function body($tab, $element, $format, $crits=array(), $cols=array(), $filters=array()) {
		if (!isset($this->lang)) $this->lang = $this->init_module('Base/Lang');
		$rb = $this->init_module('Utils/RecordBrowser', $tab, $tab.'_picker');
		$rb->adv_search = true;

		$this->element = $element;

		Libs_LeightboxCommon::display('leightbox_'.$element,
			$this->get_html_of_module($rb, array($element, $format, $crits, $cols, $filters), 'recordpicker'),
			$this->lang->t('Select'));
	}

	public function create_open_link($label) {
		return '<a '.$this->create_open_href().'>'.$label.'</a>';
	}

	public function create_open_href($button=true) {
		if(!isset($this->element))
			trigger_error('Cannot get open link/href to record picker without packing firstly.',E_USER_ERROR);
		return 'rel="leightbox_'.$this->element.'" class="lbOn'.($button?' button':'').'" onmousedown="init_all_rpicker_'.$this->element.'();"';
	}
}

?>
