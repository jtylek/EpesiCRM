<?php
require_once('HTML/QuickForm/select.php');

/**
 * HTML class for common data
 * 
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 */
class HTML_QuickForm_commondata extends HTML_QuickForm_select {
	var $_cd_root = null;
	var $_cd_prev = null;
	var $_cd_clear = array();
	var $_add_empty_fields = false;

	function HTML_QuickForm_commondata($elementName=null, $elementLabel=null, $commondata=null, $options=null, $attributes=null) {
		$this->HTML_QuickForm_select($elementName, $elementLabel, array(), $attributes);
		$this->_persistantFreeze = true;
		$this->_type = 'commondata';
		$this->_appendName = true;

		if(isset($commondata)) {
			if(is_array($commondata)) {
				$this->_cd_root = $commondata[0];
				$last=count($commondata)-1;
				for($i=1; $i<$last; $i++)
					$this->_cd_clear[] = $commondata[$i];
				$this->_cd_prev = $commondata[$last];
			} elseif(is_string($commondata))
				$this->_cd_root = $commondata;
		}
		
		if (isset($options['empty_option']))
			$this->_add_empty_fields = $options['empty_option'];

		if(!isset($this->_cd_prev) && isset($this->_cd_root)) {
			$root_data = Utils_CommonDataCommon::get_array($this->_cd_root);
			if($this->_add_empty_fields)
				$root_data = array_merge(array(''=>'---'),$root_data);
			$this->loadArray($root_data);
		}
	} //end constructor
	
	function toHtml() {
		load_js('modules/Utils/CommonData/qf.js');
		$id=$this->getAttribute('id');
		if(!isset($id)) {
			$id = $this->getName();
			$this->updateAttributes(array('id'=>$id));
		}
		eval_js('Utils_CommonData(\''.Epesi::escapeJS($id,false).'\', \''.Epesi::escapeJS($this->_cd_root,false).'\', '.json_encode($this->_cd_clear).', \''.(isset($this->_cd_prev)?Epesi::escapeJS($this->_cd_prev,false):'').'\')');
	        return parent::toHtml();
	}
} //end class HTML_QuickForm_commondata
?>
