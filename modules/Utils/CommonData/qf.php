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

	function HTML_QuickForm_commondata($elementName=null, $elementLabel=null, $commondata_root=null, $commondata_depth=1, $attributes=null, $separator=null) {
	        $this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		if (isset($separator))
			$this->_separator = $separator;
		$this->_type = 'commondata';
		$this->_appendName = true;

		if (isset($commondata_depth))
			$this->_cd_depth = $commondata_depth;
		if (isset($commondata_root))
			$this->_cd_root = $commondata_root;
	} //end constructor
	
	function _createElements() {
		$root_data = Utils_CommonDataCommon::get_array($this->_cd_root);
		$attributes = $this->getAttributes();
		if(!isset($attributes) || !is_array($attributes))
			$attributes = array();
		$name = $this->_name;

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
		eval_js('Utils_CommonData.init(\''.Epesi::escapeJS($root,false).'\', \''.Epesi::escapeJS($name,false).'\', '.$max.')');

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