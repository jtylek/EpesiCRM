<?php
require_once('HTML/QuickForm/group.php');
require_once('qf.php');

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

class HTML_QuickForm_commondata_group extends HTML_QuickForm_group {
	var $_cd_root = '';
	var $_cd_depth = 1;
	var $_add_empty_fields = false;

	function HTML_QuickForm_commondata_group($elementName=null, $elementLabel=null, $commondata=null, $options=null, $attributes=null) {
		$this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->_type = 'commondata';
		$this->_appendName = false;

		if(isset($commondata)) {
			if(is_array($commondata)) {
				if(isset($commondata['root']) && is_string($commondata['root']))
					$this->_cd_root = $commondata['root'];
				elseif(isset($commondata[0]) && is_string($commondata[0]))
					$this->_cd_root = $commondata[0];
				if(isset($commondata['depth']) && is_numeric($commondata['depth']))
					$this->_cd_depth = $commondata['depth'];
				elseif(isset($commondata[1]) && is_numeric($commondata[1]))
					$this->_cd_depth = $commondata[1];
			} elseif(is_string($commondata))
				$this->_cd_root = $commondata;
		}

		if (isset($options['depth']) && is_numeric($options['depth']))
			$this->_cd_depth = $options['depth'];

		if (isset($options['separator']) && is_string($options['separator']))
			$this->_separator = $options['separator'];

		if (isset($options['empty_option']))
			$this->_add_empty_fields = $options['empty_option'];
	} //end constructor
	
	function _createElements() {
		$name = $this->_name;
		$cd = array($this->_cd_root);
		$attributes = $this->getAttributes();
		
		$this->_elements[] = new HTML_QuickForm_commondata($name.'____0', null, $cd, $attributes);
		for($i=1; $i<$this->_cd_depth; $i++) {
			$cd[] = $name.'____'.($i-1);
			$this->_elements[] = new HTML_QuickForm_commondata($name.'____'.$i, null, $cd, $attributes);
		}
	}
	
	function exportValue(&$submitValues, $assoc = false) {
		$ret = parent::exportValue($submitValues, false);
		return $this->translateRetValues($ret);
	}
	
	function translateRetValues($ret) {
		$ret2 = array();
		foreach($ret as $k=>$v) {
			$name = explode('____',$k);
			$ret2[$name[0]][$name[1]] = $v;
		}
		return $ret2;
	}
	
	function getValue() {
		$ret = parent::getValue();
		return $this->translateRetValues($ret);
	}
} //end class HTML_QuickForm_commondata
?>