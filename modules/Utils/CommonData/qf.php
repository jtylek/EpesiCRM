<?php
/**
 * HTML class for common data
 *
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CommonData
 */
require_once('HTML/QuickForm/select.php');

class HTML_QuickForm_commondata extends HTML_QuickForm_select {
	var $_cd = null;
	var $_add_empty_fields = false;
	var $_order = 'value';

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
		
		if (isset($options['order']))
			$this->_order = Utils_CommonDataCommon::validate_order($options['order']);		
		elseif (isset($options['order_by_key'])) //legacy check
			$this->_order = Utils_CommonDataCommon::validate_order($options['order_by_key']);
		
		if(is_array($this->_cd) && count($this->_cd)==1) {
			$root_data = Utils_CommonDataCommon::get_translated_array($this->_cd[0],$this->_order);
			if($this->_add_empty_fields)
				$root_data = array(''=>'---')+$root_data;
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
			$val = $this->getValue();
			$val = $val[0];
			if($this->_flagFrozen) {
				eval_js('new Utils_CommonData_freeze(\''.Epesi::escapeJS($id,false).'\', \''.Epesi::escapeJS(json_encode($this->_cd),false).'\')');
				$html = '<span id="'.$id.'_label">&nbsp;</span>';
				$name = $this->getPrivateName();
				// Only use id attribute if doing single hidden input
				$html .= '<input' . $this->_getAttrString(array(
					     'type'  => 'hidden',
					     'name'  => $name,
					     'value' => $val,
					     'id'    => $id
					 )) . ' />';
				return $html;
			}
			eval_js('new Utils_CommonData(\''.Epesi::escapeJS($id,false).'\', \''.Epesi::escapeJS($val,false).'\', \''.Epesi::escapeJS(json_encode($this->_cd),false).'\', '.($this->_add_empty_fields?1:0).', \'' . $this->_order . '\')');
		}
	        return parent::toHtml();
	}
} //end class HTML_QuickForm_commondata
?>
