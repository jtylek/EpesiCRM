<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2010, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-RecordPicker
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_RecordPickerFS extends Module {
	private $tab,$crits,$cols,$order,$filters;
	
	public function construct($tab, $crits=array(), $cols=array(), $order=array(), $filters=array()) {
		$this->tab = $tab;
		$this->crits = $crits;
		$this->cols = $cols;
		$this->order = $order;
		$this->filters = $filters;
	}

	public function body() {
	}

	public function open() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		$x->push_main('Utils/RecordBrowser/RecordPickerFS','show',array($this->tab,$this->crits,$this->cols,$this->order,$this->filters,$this->get_path()));
	}
	
	public function back() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		$x->pop_main();
	}
	
	public function show($tab, $crits=array(), $cols=array(), $order=array(), $filters=array(),$path=null) {
		$rb = $this->init_module('Utils/RecordBrowser', $tab, $tab.'_picker');
//		$rb->adv_search = true;
		$rb->disable_actions();

		$this->display_module($rb, array($crits, $cols, $order, $filters, $path), 'recordpicker_fs');
	        Base_ActionBarCommon::add('save', __('Commit Selection'), $this->create_callback_href(array($this,'back')));
	}

	public function create_open_link($label,$form = null,$select = null) {
		return '<a '.$this->create_open_href($form,$select).'>'.$label.'</a>';
	}

	public function create_open_href($form = null,$select = null) {
		return ' href="javascript:void(0)" onClick="'.$this->create_open_href_js($form,$select).'" ';
	}

	public function create_open_href_js($form = null,$select = null,$prepend='') {
		if($form) {
			$md = md5($this->get_path());
			$form->addElement('hidden','rpfs_'.$md,0,array('id'=>'rpfs_'.$md));
			if($form->exportValue('rpfs_'.$md)) {
				if($select) {
				    $selected = $form->exportValue($select);
				    if($prepend)
				        foreach($selected as $k=>$v)
				            if(strpos($v,$prepend)===0)
				                $selected[$k] = substr($v,strlen($prepend));
				            else
				                unset($selected[$k]);
					$this->set_selected($selected);
				}
				$this->open();
			} else {
				if($select) {
				    $selected = $this->get_selected();
				    if($prepend)
				        foreach($selected as &$v)
				            $v = $prepend.$v;
					$form->setDefaults(array($select=>$selected));
				}
			}
			return '$(\'rpfs_'.$md.'\').value=1;'.$form->get_submit_form_js(false);
		} else {
			return $this->create_callback_href_js(array($this,'open'));
		}
	}
	
	public function get_selected() {
		$ret = $this->get_module_variable('selected',array());
		return array_keys($ret);
	}

	public function set_selected($s) {
		if(is_array($s) && !empty($s))
			$this->set_module_variable('selected',array_combine($s,$s));
	}

	public function clear_selected() {
		$ret = $this->set_module_variable('selected',array());
	}
}

?>
