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
	private $crits_callback = array();

	public function body($tab, $element, $format=array(), $crits=array(), $cols=array(), $order=array(), $filters=array(), $filters_defaults=array(), $custom_filters=array()) {
		Module::$disable_confirm_leave = true;
		
		$refresh = false;
		
		$this->crits_callback = is_callable($crits)? $crits: array();
		
		$multi_tab = is_array($tab);		

		$this->element = $element;

		$select_form = '';
		if ($this->crits_callback || is_array($tab)) {
			$form = $this->init_module(Libs_QuickForm::module_name());		
			
			if ($multi_tab) {
				$tabs = array_intersect_key(Utils_RecordBrowserCommon::list_installed_recordsets(), array_flip($tab));
				$form->addElement('select', 'tab', __('Recordset'), $tabs, array('id'=>'tab', 'onchange'=>$form->get_submit_form_js(), 'style'=>'width:200px'));
			}
			
			$chained_vals_element = $this->element . '__chained_vals';
			
			$form->addElement('hidden', $chained_vals_element, 1, array('id'=>$chained_vals_element));			
			
			if ($form->exportValue('submited')) {			
				$this->set_module_variable($chained_vals_element, $form->exportValue($chained_vals_element));

				if ($multi_tab)
					$this->set_module_variable('tab', $form->exportValue('tab'));
				
				$refresh = true;
			}
			
			$chained_vals = $this->get_module_variable($chained_vals_element, '');
			
			if ($multi_tab)
				$tab = $this->get_module_variable('tab', reset($tab));				

			$form->setDefaults(array('tab'=>$tab, 'chained_vals'=>$chained_vals));				

			ob_start();
			$form->display_as_row();
			$select_form = ob_get_clean();
			
			if ($this->crits_callback) {
				parse_str($chained_vals, $chained_vals_array);
				
				$crits = call_user_func($this->crits_callback, false, $chained_vals_array);

				if ($multi_tab) {
					$crits = isset($crits[$tab])? $crits[$tab]: array();
				}
			}
		}		

		$rb = $this->init_module(Utils_RecordBrowser::module_name(), array($tab, true), $tab.'_picker');
		$rb->adv_search = true;
		$rb->set_filters_defaults($filters_defaults);
		$rb->disable_actions();
		foreach($custom_filters as $field=>$arr)
		    $rb->set_custom_filter($field,$arr);

		Libs_LeightboxCommon::display(
			'rpicker_leightbox_'.$element,
			$this->get_html_of_module($rb, array($element, $format, $crits, $cols, $order, $filters, $select_form), 'recordpicker'),
			__('Select'),
			true);
		
		if ($refresh)
			eval_js('rpicker_leightbox_refresh(\'rpicker_leightbox_'.$this->element .'\');');
		
		Module::$disable_confirm_leave = false;
	}

	public function create_open_link($label) {
		return '<a '.$this->create_open_href().'>'.$label.'</a>';
	}

	public function create_open_href($button=false) {
		if(!isset($this->element))
			trigger_error('Cannot get open link/href to record picker without packing first.',E_USER_ERROR);
		
		if ($this->crits_callback)
			eval_js('rpicker_chained(\''.$this->element .'\');');

		return 'rel="rpicker_leightbox_'.$this->element.'" class="lbOn'.($button?' button':'').'"';
	}
}

?>
