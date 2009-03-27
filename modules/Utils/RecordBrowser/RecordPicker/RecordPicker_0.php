<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-RecordPicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_RecordPicker extends Module {
	private $element;

	public function body($tab, $element, $format, $crits=array(), $cols=array(), $order=array(), $filters=array()) {
		$rb = $this->init_module('Utils/RecordBrowser', $tab, $tab.'_picker');
		$rb->adv_search = true;
		$rb->disable_actions();

		$this->element = $element;

		Libs_LeightboxCommon::display(
			'leightbox_'.$element,
			$this->get_html_of_module($rb, array($element, $format, $crits, $cols, $order, $filters), 'recordpicker'),
			$this->t('Select'),
			true);
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
