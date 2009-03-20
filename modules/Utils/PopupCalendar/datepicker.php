<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
require_once("HTML/QuickForm/input.php");

class HTML_QuickForm_datepicker extends HTML_QuickForm_input {

	function HTML_QuickForm_datepicker($elementName=null, $elementLabel=null, $attributes=null) {
		HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->setType('text');
//		$this->updateAttributes(array('readonly'=>1));
	} //end constructor

	function toHtml() {
		$str = "";
		$default = $value = $this->getAttribute('value');
		if($value)
			$this->setAttribute('value',Base_RegionalSettingsCommon::time2reg($value,false,true,false));
		if ($this->_flagFrozen) {
			$str .= $this->getFrozenHtml();
		} else {
			$id = $this->getAttribute('id');
			$name = $this->getAttribute('name');
			$label = $this->getAttribute('label');
			if(!isset($id)) {
				$id = 'datepicker_field_'.$name;
				$this->updateAttributes(array('id'=>$id));
			}
			$ex_date = Base_RegionalSettingsCommon::time2reg(null,false,true,false);
			$date_format = Base_RegionalSettingsCommon::date_format();
			$str .= $this->_getTabs() . '<table style="border:0;padding:0;" cellpadding="0" cellspacing="0"><tr>'.
				'<td><input ' . $this->_getAttrString($this->_attributes) . ' '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils/PopupCalendar','Example date: %s',array($ex_date)), false ).' /></td>'.
				'<td>'.	Utils_PopupCalendarCommon::show(md5($id),
					'new Ajax.Request(\'modules/Utils/PopupCalendar/up.php\','.
					'{method:\'post\', parameters:{date: __YEAR__+\'-\'+__MONTH__+\'-\'+__DAY__},'.
					'onSuccess:function(t){$(\''.Epesi::escapeJS($id,false).'\').value=t.responseText;}})',
					false,null,null,
					'popup.clonePosition(\''.$id.'\',{setWidth:false,setHeight:false,offsetTop:$(\''.$id.'\').getHeight()})',$label,$default).'</td></tr></table>';


			load_js('modules/Utils/PopupCalendar/datepicker.js');
			eval_js('Event.observe(\''.$id.'\',\'keypress\',Utils_PopupCalendarDatePicker.validate.bindAsEventListener(Utils_PopupCalendarDatePicker,\''.Epesi::escapeJS($date_format,false).'\'))');
			eval_js('Event.observe(\''.$id.'\',\'blur\',Utils_PopupCalendarDatePicker.validate_blur.bindAsEventListener(Utils_PopupCalendarDatePicker,\''.Epesi::escapeJS($date_format,false).'\'))');
		}
		$this->setAttribute('value',$value);
		return $str;
	} //end func toHtml

/*	function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } 
		if ($value!='') $cleanValue = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
		else $cleanValue = '';
        return $this->_prepareValue($cleanValue, $assoc);
	}
	
	function onQuickFormEvent($event, $arg, &$caller)
    {
        if ('updateValue' != $event) {
            parent::onQuickFormEvent($event, $arg, $caller);
        } else {
                // constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
					if($this->_flagFrozen) 
						$this->_removeValue($caller->_submitValues);
					else {
	                    $value = $this->_findValue($caller->_submitValues);
						if($value!==null)
							$value = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
					}
                    if (null === $value) {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
        }
        return true;
    } // end func onQuickFormEvent

	function setValue($value) {
		if ($value) $this->updateAttributes(array('value'=>Base_RegionalSettingsCommon::time2reg($value,false,true,false)));
		else $this->updateAttributes(array('value'=>''));
	} // end func setValue

*/
	function getValue() {
		$value = $this->getAttribute('value');
		if(!$value) return '';
//		print('get_value('.$this->getName().')='.$value.' '.Base_RegionalSettingsCommon::time2reg($value,false,true,false).'<hr>');
		return date('Y-m-d', Base_RegionalSettingsCommon::reg2time($value,false));
	} // end func setValue

    function onQuickFormEvent($event, $arg, &$caller) {
		if($event=='updateValue')
			$caller->applyFilter($this->getName(),array($this,'reg2time'));
		return parent::onQuickFormEvent($event,$arg,$caller);
	}
	
	function reg2time($value) {
		if(!$value) return '';
		return strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
	}
} //end class HTML_QuickForm_datepicker
?>
