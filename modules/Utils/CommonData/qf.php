<?php
require_once('HTML/QuickForm/select.php');

/**
 * HTML class for common data
 * 
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 */
class HTML_QuickForm_commondata extends HTML_QuickForm_select {
	var $_cd = null;
	var $_add_empty_fields = false;

	function HTML_QuickForm_commondata($elementName=null, $elementLabel=null, $commondata=null, $options=null, $attributes=null) {
		$this->HTML_QuickForm_select($elementName, $elementLabel, array(), $attributes);
		$this->_persistantFreeze = true;
		$this->_type = 'commondata';
		$this->_appendName = true;

		if(isset($commondata)) {
			if(is_array($commondata)) {
				$this->_cd = $commondata;
			} elseif(is_string($commondata))
				$this->_cd = array($commondata);
		}
		
		if (isset($options['empty_option']))
			$this->_add_empty_fields = $options['empty_option'];

		if(count($this->_cd)==1) {
			$root_data = Utils_CommonDataCommon::get_array($this->_cd[0]);
			if($this->_add_empty_fields)
				$root_data = array_merge(array(''=>'---'),$root_data);
			$this->loadArray($root_data);
		}
	} //end constructor
	
	function toHtml() {
		if(count($this->_cd)>1) {
			load_js('modules/Utils/CommonData/qf.js');
			$id=$this->getAttribute('id');
			if(!isset($id)) {
				$id = $this->getName();
				$this->updateAttributes(array('id'=>$id));
			}
			eval_js('Utils_CommonData(\''.Epesi::escapeJS($id,false).'\', \''.Epesi::escapeJS(json_encode($this->_cd),false).'\', '.($this->_add_empty_fields?1:0).')');
		}
	        return parent::toHtml();
	}
} //end class HTML_QuickForm_commondata
?>
