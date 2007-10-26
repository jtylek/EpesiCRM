<?php
require_once('HTML/QuickForm/group.php');
require_once('HTML/QuickForm/select.php');

/**
 * HTML class for common data
 * 
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 */
class HTML_QuickForm_commondata extends HTML_QuickForm_group {
	var $_cd_root = '';
	var $_cd_depth = 1;
	var $_add_empty_fields = false;

	function HTML_QuickForm_commondata($elementName=null, $elementLabel=null, $commondata=null, $options=null, $attributes=null) {
		$this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->_type = 'commondata';
		$this->_appendName = true;

		if(isset($commondata)) {
			if(is_array($commondata)) {
				if(isset($commondata['root']) && is_string($commondata['root']))
					$this->_cd_root = $commondata['root'];
				elseif(isset($commondata[0]) && is_string($commondata[0]))
					$this->_cd_root = $commondata[0];
				if(isset($commondata['depth']) && is_numeric($commondata['depth']))
					$this->_cd_depth = $commondata['depth'];
				elseif(isset($commondata[0]) && is_numeric($commondata[0]))
					$this->_cd_depth = $commondata[0];
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
		$root_data = Utils_CommonDataCommon::get_array($this->_cd_root);
		$attributes = $this->getAttributes();
		if(!isset($attributes) || !is_array($attributes))
			$attributes = array();
		$name = $this->_name;
		
		if($this->_add_empty_fields)
			$root_data = array_merge(array(''=>'---'),$root_data);

		$this->_elements[] = & new HTML_QuickForm_select(0, null, $root_data, array_merge($attributes,array('id'=>$name.'_0')));
		for($i=1; $i<$this->_cd_depth; $i++) {
			$this->_elements[] = & new HTML_QuickForm_select($i, null, array(), array_merge($attributes,array('id'=>$name.'_'.$i)));
		}
	}
	
	function toHtml() {
		load_js('modules/Utils/CommonData/qf.js');
		$name = $this->_name;
		$root = $this->_cd_root;
		$max=count($this->getElements());
//		for($i=1,; $i<$max; $i++)
		eval_js('Utils_CommonData(\''.Epesi::escapeJS($root,false).'\', \''.Epesi::escapeJS($name,false).'\', '.$max.', '.($this->_add_empty_fields?1:0).')');

	        include_once('HTML/QuickForm/Renderer/Default.php');
	        $renderer =& new HTML_QuickForm_Renderer_Default();
	        $renderer->setElementTemplate('{element}');
	        parent::accept($renderer);
	        return $renderer->toHtml();
	}
	
	function accept(&$renderer, $required = false, $error = null) {
		$renderer->renderElement($this, $required, $error);
	} // end func accept

} //end class HTML_QuickForm_commondata
?>