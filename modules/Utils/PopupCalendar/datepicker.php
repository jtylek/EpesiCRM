<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
class HTML_QuickForm_datepicker extends HTML_QuickForm_input {

	function __construct($elementName=null, $elementLabel=null, $attributes=null) {
		parent::__construct($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->setType('datepicker');
//		$this->updateAttributes(array('readonly'=>1));
	} //end constructor

	function toHtml() {
		$str = "";
		if ($this->_flagFrozen) {
			$str .= $this->getFrozenHtml();
		} else {
			$value = $this->getAttribute('value');
			if (is_numeric($value)) $value = date('Y-m-d', $value);
			$id = $this->getAttribute('id');
			$name = $this->getAttribute('name');
			if ($value) $this->setAttribute('value',Base_RegionalSettingsCommon::time2reg($value,false,true,false));
			if(!isset($id)) {
				$id = 'datepicker_field_'.$name;
				$this->updateAttributes(array('id'=>$id));
			}
			$ex_date = Base_RegionalSettingsCommon::time2reg(null,false,true,false);
			$date_format = Base_RegionalSettingsCommon::date_format();
			$this->setType('text');
            if (!$this->getAttribute('placeholder'))
                $this->setAttribute('placeholder', __('Click to select date'));
            $js = Utils_PopupCalendarCommon::create_href(md5($id),
                    'new jq.ajax(\'modules/Utils/PopupCalendar/up.php\','.
                    '{method:\'post\', data:{date: __YEAR__+\'-\'+__MONTH__+\'-\'+__DAY__},'.
                    'success:function(t){e=jq(\'#'.Epesi::escapeJS($id,false).'\');if(e.length) {e.val(t);e.change();}}})',
                    null,null,
                    'jq(popup).clonePosition(\'#'.$id.'\',{cloneWidth:false,cloneHeight:false,offsetTop:jq(\'#'.$id.'\').height()})',$value, $id);
            $str .= '<input ' . $js . ' ' . $this->_getAttrString($this->_attributes) . ' '.Utils_TooltipCommon::open_tag_attrs(__('Example date: %s',array($ex_date)), false ).' />';
			eval_js('jq(\'#'.$id.'\').keypress(function(ev){Utils_PopupCalendarDatePicker.validate(ev,\''.Epesi::escapeJS($date_format,false).'\')})');
			eval_js('jq(\'#'.$id.'\').blur(function(ev){Utils_PopupCalendarDatePicker.validate_blur(ev,\''.Epesi::escapeJS($date_format,false).'\')})');
		}
		return $str;
	} //end func toHtml

	function getValue() {
		$value = $this->getAttribute('value');
		if (!$value) return $value;
//		print('get_value('.$this->getName().')='.$value.' '.Base_RegionalSettingsCommon::time2reg($value,false,true,false).'<hr>');
                if(!is_numeric($value) && is_string($value) && !strtotime($value)) return $value;
		return Base_RegionalSettingsCommon::time2reg($value,false,true,false);
	} // end func setValue

	function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } 
		if ($value=='') return $this->_prepareValue('', $assoc);
		$cleanValue = date('Y-m-d',Base_RegionalSettingsCommon::reg2time($value,false));
		return $this->_prepareValue($cleanValue, $assoc);
	}

    function onQuickFormEvent($event, $arg, &$caller) {
		if($event=='updateValue' && is_callable(array($caller,'applyFilter')))
			$caller->applyFilter($this->getName(),array($this,'reg2time'));
		return parent::onQuickFormEvent($event,$arg,$caller);
	}
	
	function reg2time($value) {
		if (!$value) return '';
		return strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
	}
} //end class HTML_QuickForm_datepicker
?>
